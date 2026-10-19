<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarSearch, Store, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookingHero from '@/components/booking/BookingHero.vue';
import BusinessDetails from '@/components/booking/BusinessDetails.vue';
import DayStrip from '@/components/booking/DayStrip.vue';
import ServicePicker from '@/components/booking/ServicePicker.vue';
import StaffPicker from '@/components/booking/StaffPicker.vue';
import StepBadge from '@/components/booking/StepBadge.vue';
import TimeGrid from '@/components/booking/TimeGrid.vue';
import HalfpastMark from '@/components/brand/HalfpastMark.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { timeIn, zoneCity } from '@/lib/booking';
import { formatDate } from '@/lib/dates';
import { visitorZone } from '@/lib/hydration';
import { formatMoney } from '@/lib/money';
import type { WeekHours } from '@/lib/openingHours';
import { duration } from '@/lib/time';
import type {
    BookableService,
    BookableStaff,
    BookableTime,
    BookingDay,
    BookingSelection,
} from '@/types/booking';

const props = defineProps<{
    services: BookableService[];
    staff: BookableStaff[];
    today: string;
    lastDay: string;
    holdMinutes: number;
    selection: BookingSelection;
    days: BookingDay[] | null;
    times: BookableTime[] | null;
    hours: WeekHours;
    serverNow: string;
}>();

const page = usePage();
const business = computed(() => page.props.business!);

const loading = ref(false);
const pickedTime = ref<string | null>(null);

const service = computed(
    () =>
        props.services.find((s) => s.id === props.selection.service_id) ?? null,
);
const people = computed(() =>
    service.value
        ? props.staff.filter((m) => service.value!.staff_ids.includes(m.id))
        : [],
);
const person = computed(
    () => props.staff.find((m) => m.id === props.selection.staff_id) ?? null,
);

// Times in the customer's own zone, or the business's if they'd rather.
const customerZone = computed(() => visitorZone(business.value.timezone));
const sameZone = computed(() => customerZone.value === business.value.timezone);
const useBusinessZone = ref(false);
const zone = computed(() =>
    useBusinessZone.value || sameZone.value
        ? business.value.timezone
        : customerZone.value,
);

const picked = computed(
    () => props.times?.find((t) => t.starts_at === pickedTime.value) ?? null,
);

// Holding the picked time. If someone got there first, the page comes back
// with fresh times and the nearest free ones are offered instead.
const holding = ref(false);
const takenTime = ref<string | null>(null);
const holdError = computed(() => page.props.errors?.starts_at ?? null);

const nearby = computed(() => {
    if (!takenTime.value || !props.times) {
        return [];
    }

    const wanted = new Date(takenTime.value).getTime();

    return [...props.times]
        .sort(
            (a, b) =>
                Math.abs(new Date(a.starts_at).getTime() - wanted) -
                Math.abs(new Date(b.starts_at).getTime() - wanted),
        )
        .slice(0, 3)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
});

function hold(): void {
    if (!picked.value || !service.value) {
        return;
    }

    const wanted = picked.value.starts_at;

    router.post(
        route('booking.hold', { business: business.value.slug }),
        {
            service_id: service.value.id,
            staff_id: props.selection.staff_id,
            starts_at: wanted,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (holding.value = true),
            onFinish: () => (holding.value = false),
            onError: () => {
                takenTime.value = wanted;
                pickedTime.value = null;
            },
        },
    );
}

function pickTime(startsAt: string): void {
    pickedTime.value = startsAt;
    takenTime.value = null;
}

/** Change part of the choice and reload only the days and times. */
function choose(
    changes: Partial<
        Record<'service' | 'staff' | 'from' | 'date', string | number | null>
    >,
): void {
    const query = {
        service: props.selection.service_id,
        staff: props.selection.staff_id,
        from: props.selection.from,
        date: props.selection.date,
        ...changes,
    };

    router.get(
        route('booking.show', { business: business.value.slug }),
        Object.fromEntries(
            Object.entries(query).filter(([, value]) => value !== null),
        ),
        {
            only: ['selection', 'days', 'times'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

function pickService(id: number): void {
    if (id === props.selection.service_id) {
        return;
    }

    pickedTime.value = null;
    // Keep the chosen person if they do the new service too.
    const next = props.services.find((s) => s.id === id);
    const keep =
        props.selection.staff_id !== null &&
        next?.staff_ids.includes(props.selection.staff_id);

    choose({
        service: id,
        staff: keep ? props.selection.staff_id : null,
        date: null,
    });
}

function pickStaff(id: number | null): void {
    pickedTime.value = null;
    choose({ staff: id, date: null });
}

function pickDate(date: string): void {
    pickedTime.value = null;
    choose({ date });
}

function page14(from: string): void {
    pickedTime.value = null;
    choose({ from, date: null });
}

// The next day in view with a free time, for an empty day.
const nextFreeDay = computed(
    () =>
        props.days?.find(
            (d) => d.free > 0 && d.date > (props.selection.date ?? ''),
        ) ?? null,
);

const whoLabel = computed(() => {
    if (person.value) {
        return person.value.name;
    }

    if (!picked.value) {
        return people.value.length === 1
            ? people.value[0].name
            : 'whoever is free';
    }

    const free = people.value.filter((m) =>
        picked.value!.staff_ids.includes(m.id),
    );

    return free.length === 1
        ? free[0].name
        : `whoever is free (${free.length} people)`;
});

// Scroll on to the next step once something is picked, on smaller screens.
const timesSection = ref<HTMLElement | null>(null);
watch(
    () => props.selection.service_id,
    (id, before) => {
        if (id && before !== undefined && window.innerWidth < 1024) {
            setTimeout(
                () =>
                    timesSection.value?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    }),
                350,
            );
        }
    },
);
</script>

<template>
    <Head :title="`Book with ${business.name}`" />

    <div class="min-h-svh bg-background pb-32 text-foreground lg:pb-16">
        <BookingHero :hours="hours" :server-now="serverNow" />

        <main
            class="mx-auto grid max-w-5xl gap-10 px-4 py-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
        >
            <div v-if="services.length === 0" class="lg:col-span-2">
                <div
                    class="flex flex-col items-center rounded-2xl border border-dashed px-6 py-16 text-center"
                >
                    <span
                        class="grid size-12 place-items-center rounded-full bg-muted"
                    >
                        <Store class="size-5 text-muted-foreground" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold">
                        Not taking online bookings yet
                    </h2>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        {{ business.name }} hasn't opened its diary here yet.
                        Check back soon.
                    </p>
                </div>
            </div>

            <div v-else class="min-w-0 space-y-10">
                <section aria-labelledby="step-service">
                    <h2
                        id="step-service"
                        class="mb-4 flex items-center gap-3 text-lg font-semibold"
                    >
                        <StepBadge :step="1" :done="service !== null" />
                        What would you like?
                    </h2>
                    <ServicePicker
                        :services="services"
                        :selected="selection.service_id"
                        :currency="business.currency"
                        @select="pickService"
                    />
                </section>

                <section
                    v-if="service && people.length > 1"
                    class="animate-in duration-300 fade-in slide-in-from-bottom-2"
                    aria-labelledby="step-staff"
                >
                    <h2
                        id="step-staff"
                        class="mb-4 flex items-center gap-3 text-lg font-semibold"
                    >
                        <StepBadge
                            :step="2"
                            :done="
                                selection.staff_id !== null || picked !== null
                            "
                        />
                        Who with?
                    </h2>
                    <StaffPicker
                        :staff="people"
                        :selected="selection.staff_id"
                        @select="pickStaff"
                    />
                </section>

                <section
                    v-if="service && days"
                    ref="timesSection"
                    class="animate-in scroll-mt-6 duration-300 fade-in slide-in-from-bottom-2"
                    aria-labelledby="step-time"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2
                            id="step-time"
                            class="flex items-center gap-3 text-lg font-semibold"
                        >
                            <StepBadge
                                :step="people.length > 1 ? 3 : 2"
                                :done="picked !== null"
                            />
                            When suits you?
                        </h2>

                        <div
                            v-if="!sameZone"
                            class="inline-flex rounded-full border p-0.5 text-xs"
                            role="radiogroup"
                            aria-label="Show times in"
                        >
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="!useBusinessZone"
                                class="rounded-full px-3 py-1 transition-colors"
                                :class="
                                    !useBusinessZone
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground'
                                "
                                @click="useBusinessZone = false"
                            >
                                Your time ({{ zoneCity(customerZone) }})
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="useBusinessZone"
                                class="rounded-full px-3 py-1 transition-colors"
                                :class="
                                    useBusinessZone
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground'
                                "
                                @click="useBusinessZone = true"
                            >
                                {{ zoneCity(business.timezone) }} time
                            </button>
                        </div>
                    </div>

                    <DayStrip
                        :days="days"
                        :selected="selection.date"
                        :today="today"
                        :last-day="lastDay"
                        :from="selection.from"
                        @select="pickDate"
                        @page="page14"
                    />

                    <div
                        v-if="holdError && takenTime"
                        class="mt-6 flex animate-in gap-3 rounded-2xl border border-sun-deep/30 bg-sun/10 p-4 fade-in slide-in-from-top-1"
                        role="alert"
                        data-test="hold-error"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-4 shrink-0 text-sun-deep"
                        />
                        <div class="min-w-0 text-sm">
                            <p class="font-medium">
                                {{ timeIn(takenTime, zone) }} isn't free any
                                more. Someone may have just booked it.
                            </p>
                            <p
                                v-if="nearby.length"
                                class="mt-1 text-muted-foreground"
                            >
                                Still free close by:
                            </p>
                            <div
                                v-if="nearby.length"
                                class="mt-2 flex flex-wrap gap-2"
                            >
                                <button
                                    v-for="time in nearby"
                                    :key="time.starts_at"
                                    type="button"
                                    class="rounded-full border bg-background px-3 py-1 text-sm font-medium font-time transition-colors hover:border-foreground/40"
                                    @click="pickTime(time.starts_at)"
                                >
                                    {{ timeIn(time.starts_at, zone) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mt-6 min-h-40 transition-opacity duration-200"
                        :class="loading && 'opacity-50'"
                        aria-live="polite"
                        :aria-busy="loading"
                    >
                        <Spinner
                            v-if="loading"
                            class="absolute top-4 left-1/2 -translate-x-1/2"
                        />
                        <TimeGrid
                            v-if="times && times.length > 0"
                            :times="times"
                            :zone="zone"
                            :selected="pickedTime"
                            :day-key="`${selection.date}-${selection.staff_id}-${selection.service_id}`"
                            @select="pickTime"
                        />
                        <div
                            v-else-if="times"
                            class="flex flex-col items-center rounded-2xl border border-dashed px-6 py-10 text-center"
                        >
                            <CalendarSearch
                                class="size-6 text-muted-foreground"
                            />
                            <p class="mt-3 font-medium">
                                {{
                                    selection.date
                                        ? `Nothing free on ${formatDate(selection.date, { weekday: 'long', day: 'numeric', month: 'long' })}`
                                        : 'Nothing free in these two weeks'
                                }}
                            </p>
                            <Button
                                v-if="nextFreeDay"
                                type="button"
                                variant="outline"
                                class="mt-4 rounded-full"
                                @click="pickDate(nextFreeDay.date)"
                            >
                                Try
                                {{
                                    formatDate(nextFreeDay.date, {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'short',
                                    })
                                }}
                            </Button>
                            <Button
                                v-else-if="
                                    days.length &&
                                    days[days.length - 1].date < lastDay
                                "
                                type="button"
                                variant="outline"
                                class="mt-4 rounded-full"
                                @click="page14(days[days.length - 1].date)"
                            >
                                Look further ahead
                            </Button>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-muted-foreground">
                        Times are
                        {{
                            zone === customerZone && !sameZone
                                ? 'in your time zone'
                                : `${zoneCity(business.timezone)} time`
                        }}.
                    </p>
                </section>
            </div>

            <aside
                v-if="services.length > 0"
                class="hidden rounded-2xl border bg-card p-5 lg:sticky lg:top-6 lg:block"
                aria-label="Your booking"
            >
                <p
                    class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Your booking
                </p>
                <template v-if="service">
                    <p class="mt-3 text-lg font-semibold">{{ service.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ duration(service.duration_minutes) }} with
                        {{ whoLabel }}
                    </p>
                    <div
                        class="mt-4 rounded-xl bg-muted px-3 py-2.5 text-sm"
                        data-test="summary-when"
                    >
                        <template v-if="picked && selection.date">
                            <p class="font-medium">
                                {{
                                    formatDate(selection.date, {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })
                                }}
                            </p>
                            <p class="font-time">
                                {{ timeIn(picked.starts_at, zone) }}
                                <span class="text-muted-foreground"
                                    >· {{ zoneCity(zone) }}</span
                                >
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            Pick a day and a time.
                        </p>
                    </div>
                    <dl class="mt-4 space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Price</dt>
                            <dd class="font-time">
                                {{
                                    formatMoney(
                                        service.price,
                                        business.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">To pay now</dt>
                            <dd class="font-semibold font-time">
                                {{
                                    service.deposit
                                        ? formatMoney(
                                              service.deposit,
                                              business.currency,
                                          )
                                        : 'Nothing'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <Button
                        type="button"
                        size="lg"
                        class="mt-5 w-full rounded-xl"
                        :disabled="!picked || holding"
                        data-test="hold"
                        @click="hold"
                    >
                        <Spinner v-if="holding" />
                        Hold this time
                    </Button>
                    <p class="mt-2 text-center text-xs text-muted-foreground">
                        We'll keep it for you for {{ holdMinutes }} minutes
                        while you finish.
                    </p>
                </template>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Choose a service to see free times.
                </p>
            </aside>
        </main>

        <!-- On a phone the summary sits at the bottom once a time is picked. -->
        <Transition
            enter-from-class="translate-y-full"
            leave-to-class="translate-y-full"
            enter-active-class="transition-transform duration-300 ease-out"
            leave-active-class="transition-transform duration-200 ease-in"
        >
            <div
                v-if="service && picked && selection.date"
                class="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden"
                data-test="mobile-summary"
            >
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">
                            <span class="font-semibold">{{
                                service.name
                            }}</span>
                            · {{ formatDate(selection.date) }} ·
                            <span class="font-time">{{
                                timeIn(picked.starts_at, zone)
                            }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            with {{ whoLabel }} ·
                            {{
                                service.deposit
                                    ? `${formatMoney(service.deposit, business.currency)} deposit`
                                    : 'nothing to pay now'
                            }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="shrink-0 rounded-xl"
                        :disabled="holding"
                        data-test="hold-mobile"
                        @click="hold"
                    >
                        <Spinner v-if="holding" />
                        Hold it
                    </Button>
                </div>
            </div>
        </Transition>

        <BusinessDetails
            v-if="services.length > 0"
            :staff="staff"
            :hours="hours"
        />

        <footer
            class="mx-auto flex max-w-5xl items-center gap-2 px-4 text-xs text-muted-foreground"
        >
            <HalfpastMark :size="16" />
            Bookings by
            <Link :href="route('home')" class="font-medium text-foreground"
                >Halfpast</Link
            >
            <span aria-hidden="true">·</span>
            <Link :href="route('privacy')" class="hover:text-foreground"
                >Privacy</Link
            >
        </footer>
    </div>
</template>
