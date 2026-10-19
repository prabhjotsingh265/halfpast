<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CircleAlert,
    Copy,
    ExternalLink,
    UserX,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookingList from '@/components/calendar/BookingList.vue';
import BookingSheet from '@/components/calendar/BookingSheet.vue';
import LiveBadge from '@/components/calendar/LiveBadge.vue';
import TimeOffSheet from '@/components/calendar/TimeOffSheet.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useLiveCalendar } from '@/composables/useLiveCalendar';
import { useNow } from '@/composables/useTimeScale';
import { dateIn, hourIn, timeIn } from '@/lib/booking';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
} from '@/types/calendar';

type RecentBooking = CalendarBooking & { booked_at: string | null };

const props = defineProps<{
    today: string;
    timezone: string;
    isOwner: boolean;
    /** False for staff whose login isn't tied to a team profile yet. */
    linked: boolean;
    /** The team members this person is (for staff, themselves). */
    myStaffIds: number[];
    bookingPageUrl: string;
    team: { id: number; name: string }[];
    stats: {
        booked: number;
        left: number;
        onNow: number;
        toTake: number;
        weekBookings: number;
        weekValue: number;
        currency: string;
    };
    next: CalendarBooking | null;
    bookings: CalendarBooking[];
    timeOff: CalendarTimeOff[];
    unmarked: CalendarBooking[];
    /** All of them, of which `unmarked` is the latest few. */
    unmarkedCount: number;
    recent: RecentBooking[];
    attention: {
        failedRefunds: {
            amount: number;
            currency: string;
            customer: string | null;
            gateway: string;
        }[];
        blockedServices: string[];
    } | null;
    setup: {
        services: boolean;
        team: boolean;
        hours: boolean;
        assigned: boolean;
        payments: boolean;
        profile: boolean;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Today', href: route('dashboard') }],
    },
});

const page = usePage();
const now = useNow();

// A new booking anywhere can change today, the week or the recent list.
useLiveCalendar({
    only: [
        'stats',
        'next',
        'bookings',
        'timeOff',
        'unmarked',
        'unmarkedCount',
        'recent',
    ],
});

const greeting = computed(() => {
    const hour = hourIn(now.value, props.timezone);
    const name = page.props.auth.user?.name.split(' ')[0] ?? '';
    const part =
        hour < 12
            ? 'Good morning'
            : hour < 17
              ? 'Good afternoon'
              : 'Good evening';

    return name ? `${part}, ${name}` : part;
});

const dateLine = computed(
    () =>
        `${formatDate(props.today, { weekday: 'long', day: 'numeric', month: 'long' })} · ${page.props.business?.name ?? ''}`,
);

const names = computed(
    () => new Map(props.team.map((member) => [member.id, member.name])),
);

/** BookingList takes team members with hours; only their names are used here. */
const listStaff = computed<CalendarStaff[]>(() =>
    props.team.map((member) => ({ ...member, hours: {} })),
);

const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

function fromNow(instant: string): string {
    const minutes = Math.round(
        (new Date(instant).getTime() - now.value.getTime()) / 60_000,
    );

    if (Math.abs(minutes) < 60) {
        return relative.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    return Math.abs(hours) < 24
        ? relative.format(hours, 'hour')
        : relative.format(Math.round(hours / 24), 'day');
}

function dayAndTime(instant: string): string {
    const day = dateIn(instant, props.timezone);

    return `${day === props.today ? 'Today' : formatDate(day)} · ${timeIn(instant, props.timezone)}`;
}

// The booking and time-off panels, as on the calendar.
const selected = ref<CalendarBooking | null>(null);
const sheetOpen = ref(false);
const offSelected = ref<CalendarTimeOff | null>(null);
const offOpen = ref(false);

function open(booking: CalendarBooking): void {
    selected.value = booking;
    sheetOpen.value = true;
}

function openOff(entry: CalendarTimeOff): void {
    offSelected.value = entry;
    offOpen.value = true;
}

// After an action the lists reload: show the open booking as it is now, or
// close the panel if it's no longer here.
watch(
    () => [props.bookings, props.unmarked, props.recent] as const,
    (lists) => {
        if (!selected.value) {
            return;
        }

        const fresh = lists.flat().find((b) => b.id === selected.value?.id);

        if (fresh) {
            selected.value = fresh;
        } else {
            sheetOpen.value = false;
        }
    },
);

watch(
    () => props.timeOff,
    (entries) => {
        if (
            offSelected.value &&
            !entries.some((e) => e.id === offSelected.value?.id)
        ) {
            offOpen.value = false;
        }
    },
);

const marking = ref<number | null>(null);

function mark(booking: CalendarBooking, outcome: 'complete' | 'no-show'): void {
    router.post(
        route(
            outcome === 'complete' ? 'bookings.complete' : 'bookings.no-show',
            booking.code,
        ),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (marking.value = booking.id),
            onFinish: () => (marking.value = null),
        },
    );
}

const copied = ref(false);

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.bookingPageUrl);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        // No clipboard access: the link is on screen to copy by hand.
    }
}

const steps = computed(() =>
    props.setup
        ? [
              {
                  done: props.setup.services,
                  label: 'Add the services you offer',
                  href: route('services.create'),
                  action: 'Add a service',
              },
              {
                  done: props.setup.team,
                  label: 'Add the people who do them',
                  href: route('staff.create'),
                  action: 'Add someone',
              },
              {
                  done: props.setup.hours,
                  label: 'Set their working hours',
                  href: route('staff.index'),
                  action: 'Set hours',
              },
              {
                  done: props.setup.assigned,
                  label: 'Choose who does each service',
                  href: route('services.index'),
                  action: 'Choose',
              },
              {
                  done: props.setup.payments,
                  label: 'Take deposits online (optional)',
                  href: route('business.payments.edit'),
                  action: 'Set up',
              },
              {
                  done: props.setup.profile,
                  label: 'Add your logo and a line about you (optional)',
                  href: route('business.profile.edit'),
                  action: 'Add',
              },
          ]
        : [],
);

const stepsDone = computed(
    () => steps.value.slice(0, 4).filter((step) => step.done).length,
);

const hasAttention = computed(
    () =>
        props.unmarked.length > 0 ||
        (props.attention?.failedRefunds.length ?? 0) > 0 ||
        (props.attention?.blockedServices.length ?? 0) > 0,
);

const gatewayName: Record<string, string> = {
    stripe: 'Stripe',
    razorpay: 'Razorpay',
    test: 'the test gateway',
};

function listOf(items: string[]): string {
    return new Intl.ListFormat(undefined, {
        style: 'long',
        type: 'conjunction',
    }).format(items);
}

const staffName = computed(
    () => names.value.get(selected.value?.staff_id ?? 0) ?? null,
);
const offStaffName = computed(
    () => names.value.get(offSelected.value?.staff_id ?? 0) ?? null,
);
</script>

<template>
    <Head title="Today" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading :title="greeting" :description="dateLine" />
            <LiveBadge />
        </div>

        <!-- Until the basics are in place, the next step comes first. -->
        <section
            v-if="setup"
            class="rounded-xl border bg-card p-5"
            data-test="setup"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Get ready to take bookings</h2>
                <p class="text-sm text-muted-foreground">
                    {{ stepsDone }} of 4 done
                </p>
            </div>
            <ol class="mt-4 divide-y">
                <li
                    v-for="(step, index) in steps"
                    :key="step.label"
                    class="flex items-center gap-3 py-2.5"
                >
                    <span
                        class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold"
                        :class="
                            step.done
                                ? 'bg-success text-white'
                                : 'border text-muted-foreground'
                        "
                    >
                        <Check v-if="step.done" class="size-3.5" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span
                        class="flex-1 text-sm"
                        :class="
                            step.done && 'text-muted-foreground line-through'
                        "
                        >{{ step.label }}</span
                    >
                    <Button
                        v-if="!step.done"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <Link :href="step.href">{{ step.action }}</Link>
                    </Button>
                </li>
            </ol>
        </section>

        <p
            v-if="!linked"
            class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
        >
            Your login isn't linked to anyone on the team yet, so there's no
            diary to show. Ask the owner to link it on the Team page.
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-test="stats">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Booked today</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.booked }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <template v-if="stats.booked === 0">A quiet day</template>
                    <template v-else-if="stats.left === 0"
                        >All done for today</template
                    >
                    <template v-else
                        >{{ stats.left }} still to come<template
                            v-if="stats.onNow > 0"
                        >
                            · {{ stats.onNow }} on now</template
                        ></template
                    >
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl border bg-card p-4 text-left transition-colors enabled:hover:bg-muted/50 disabled:cursor-default"
                :disabled="!next"
                data-test="next-up"
                @click="next && open(next)"
            >
                <p class="text-xs text-muted-foreground">Next up</p>
                <template v-if="next">
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ timeIn(next.starts_at, timezone) }}
                    </p>
                    <p class="mt-1 truncate text-xs text-muted-foreground">
                        {{ fromNow(next.starts_at) }} ·
                        {{ next.service_name }}
                        <template v-if="next.customer"
                            >· {{ next.customer.name }}</template
                        >
                    </p>
                </template>
                <template v-else>
                    <p class="mt-1 text-3xl font-semibold">—</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Nothing else today
                    </p>
                </template>
            </button>

            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Still to take today</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ formatMoney(stats.toTake, stats.currency) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    In the salon, after deposits paid
                </p>
            </div>

            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">This week</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.weekBookings }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ stats.weekBookings === 1 ? 'booking' : 'bookings' }},
                    {{ formatMoney(stats.weekValue, stats.currency) }} in all
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section aria-label="Today's appointments" data-test="today">
                <BookingList
                    :days="[today]"
                    :today="today"
                    :timezone="timezone"
                    :staff="
                        isOwner
                            ? listStaff
                            : listStaff.filter((m) => myStaffIds.includes(m.id))
                    "
                    :bookings="bookings"
                    :time-off="timeOff"
                    @open="open"
                    @off="openOff"
                />
                <Link
                    :href="route('calendar')"
                    class="mt-3 inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Open the calendar <ArrowRight class="size-3.5" />
                </Link>
            </section>

            <aside class="space-y-6">
                <section
                    v-if="hasAttention"
                    class="rounded-xl border bg-card p-4"
                    data-test="attention"
                >
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <CircleAlert class="size-4 text-sun-deep" />
                        Needs a look
                    </h2>

                    <div v-if="unmarked.length > 0" class="mt-3">
                        <p class="text-sm text-muted-foreground">
                            How did these go?
                            <template v-if="unmarkedCount > unmarked.length"
                                >The latest {{ unmarked.length }} of
                                {{ unmarkedCount }}.</template
                            >
                        </p>
                        <ul class="mt-2 divide-y" data-test="unmarked">
                            <li
                                v-for="booking in unmarked"
                                :key="booking.id"
                                class="flex items-center gap-2 py-2"
                            >
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left text-sm hover:underline"
                                    @click="open(booking)"
                                >
                                    <span class="block truncate font-medium">{{
                                        booking.customer?.name ?? 'Walk-in'
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ dayAndTime(booking.starts_at) }} ·
                                        {{ booking.service_name }}</span
                                    >
                                </button>
                                <template v-if="booking.can.mark">
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        class="size-8"
                                        :disabled="marking === booking.id"
                                        :aria-label="`${booking.customer?.name ?? 'Walk-in'} came: mark completed`"
                                        data-test="quick-complete"
                                        @click="mark(booking, 'complete')"
                                    >
                                        <Check class="size-4" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        class="size-8"
                                        :disabled="marking === booking.id"
                                        :aria-label="`${booking.customer?.name ?? 'Walk-in'} didn't come: mark a no-show`"
                                        data-test="quick-no-show"
                                        @click="mark(booking, 'no-show')"
                                    >
                                        <UserX class="size-4" />
                                    </Button>
                                </template>
                            </li>
                        </ul>
                    </div>

                    <p
                        v-for="(refund, index) in attention?.failedRefunds ??
                        []"
                        :key="`refund-${index}`"
                        class="mt-3 rounded-lg bg-muted px-3 py-2 text-sm"
                    >
                        A
                        {{ formatMoney(refund.amount, refund.currency) }}
                        refund<template v-if="refund.customer">
                            to {{ refund.customer }}</template
                        >
                        hasn't gone through after several tries. Check the
                        payment in
                        {{ gatewayName[refund.gateway] ?? refund.gateway }}.
                    </p>

                    <p
                        v-if="(attention?.blockedServices.length ?? 0) > 0"
                        class="mt-3 rounded-lg bg-muted px-3 py-2 text-sm"
                        data-test="blocked-services"
                    >
                        Online booking is off for
                        {{ listOf(attention!.blockedServices) }}: they take a
                        deposit and no way to pay is switched on.
                        <Link
                            :href="route('business.payments.edit')"
                            class="font-medium underline underline-offset-4"
                            >Switch on payments</Link
                        >
                    </p>
                </section>

                <section
                    class="rounded-xl border bg-card p-4"
                    data-test="recent"
                >
                    <h2 class="text-sm font-semibold">Booked online lately</h2>
                    <ul v-if="recent.length > 0" class="mt-2 divide-y">
                        <li v-for="booking in recent" :key="booking.id">
                            <button
                                type="button"
                                class="flex w-full items-baseline gap-3 py-2 text-left text-sm hover:underline"
                                @click="open(booking)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{
                                        booking.customer?.name ?? 'Someone'
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ booking.service_name }},
                                        {{
                                            dayAndTime(booking.starts_at)
                                        }}</span
                                    >
                                </span>
                                <span
                                    v-if="booking.booked_at"
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ fromNow(booking.booked_at) }}</span
                                >
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Nothing yet. Share your booking page to get the first
                        one.
                    </p>
                </section>

                <section class="rounded-xl border bg-card p-4">
                    <h2 class="text-sm font-semibold">Your booking page</h2>
                    <p
                        class="mt-2 truncate rounded-md bg-muted px-2.5 py-1.5 font-mono text-xs"
                        data-test="booking-page-url"
                    >
                        {{ bookingPageUrl }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="copy-link"
                            @click="copyLink"
                        >
                            <Check v-if="copied" class="size-4" />
                            <Copy v-else class="size-4" />
                            {{ copied ? 'Copied' : 'Copy link' }}
                        </Button>
                        <Button as-child size="sm" variant="ghost">
                            <a
                                :href="bookingPageUrl"
                                target="_blank"
                                rel="noopener"
                                ><ExternalLink class="size-4" />Open</a
                            >
                        </Button>
                    </div>
                </section>
            </aside>
        </div>

        <BookingSheet
            v-model:open="sheetOpen"
            :booking="selected"
            :timezone="timezone"
            :staff-name="staffName"
        />
        <TimeOffSheet
            v-model:open="offOpen"
            :entry="offSelected"
            :staff-name="offStaffName"
            :timezone="timezone"
            :business-name="page.props.business?.name ?? ''"
        />
    </div>
</template>
