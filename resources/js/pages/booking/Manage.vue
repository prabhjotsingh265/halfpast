<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarPlus,
    CircleX,
    Clock3,
    Mail,
    MapPin,
    Phone,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BookingHeader from '@/components/booking/BookingHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { timeIn, zoneCity } from '@/lib/booking';
import { displayLocale, visitorZone } from '@/lib/hydration';
import { formatMoney } from '@/lib/money';
import { duration } from '@/lib/time';
import type { CancellationOffer, ManagedBooking } from '@/types/booking';

const props = defineProps<{
    booking: ManagedBooking;
    cancellation: CancellationOffer | null;
}>();

const business = computed(() => usePage().props.business!);

const zone = computed(() => visitorZone(business.value.timezone));
const sameZone = computed(() => zone.value === business.value.timezone);

const date = computed(() =>
    new Intl.DateTimeFormat(displayLocale(), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: zone.value,
    }).format(new Date(props.booking.starts_at)),
);
const length = computed(
    () =>
        (new Date(props.booking.ends_at).getTime() -
            new Date(props.booking.starts_at).getTime()) /
        60_000,
);

const page = usePage();
const cancelError = computed(() => page.props.errors?.booking ?? null);
const cancelling = ref(false);

const freeUntil = computed(() =>
    props.cancellation
        ? new Intl.DateTimeFormat(displayLocale(), {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
              hour: 'numeric',
              minute: '2-digit',
              timeZone: zone.value,
          }).format(new Date(props.cancellation.free_until))
        : '',
);

/** What happens to the deposit, said before the customer confirms. */
const cancelOutcome = computed(() => {
    const offer = props.cancellation;

    if (!offer || props.booking.deposit === 0) {
        return 'There’s nothing to pay or refund.';
    }

    return offer.refund > 0
        ? `Your ${formatMoney(offer.refund, props.booking.currency)} deposit will be refunded in full.`
        : `It’s less than ${offer.window_hours} hours away, so ${business.value.name} keeps the ${formatMoney(offer.kept, props.booking.currency)} deposit.`;
});

function cancel(): void {
    if (!props.cancellation) {
        return;
    }

    router.post(
        props.cancellation.url,
        {},
        {
            preserveScroll: true,
            onStart: () => (cancelling.value = true),
            onFinish: () => (cancelling.value = false),
        },
    );
}

const headline = computed(
    () =>
        ({
            confirmed: 'You’re booked.',
            held: 'Almost there.',
            cancelled: 'This booking was cancelled.',
            completed: 'Thanks for coming.',
            no_show: 'This booking was missed.',
            expired: 'This booking wasn’t finished.',
        })[props.booking.status],
);
</script>

<template>
    <Head :title="`${booking.service_name} with ${business.name}`" />

    <div class="min-h-svh bg-background pb-16 text-foreground">
        <BookingHeader compact />

        <main class="mx-auto max-w-xl px-4 py-10">
            <div class="flex flex-col items-center text-center">
                <svg
                    v-if="booking.status === 'confirmed'"
                    class="booked-check size-20"
                    viewBox="0 0 80 80"
                    aria-hidden="true"
                >
                    <circle cx="40" cy="40" r="36" fill="var(--sun)" />
                    <path
                        d="M24 41l11 11 22-24"
                        fill="none"
                        stroke="#15181F"
                        stroke-width="6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        pathLength="1"
                    />
                </svg>
                <span
                    v-else
                    class="grid size-16 place-items-center rounded-full bg-muted"
                >
                    <Clock3
                        v-if="booking.status === 'held'"
                        class="size-7 text-muted-foreground"
                    />
                    <CircleX v-else class="size-7 text-muted-foreground" />
                </span>

                <h1
                    class="mt-6 animate-in text-3xl font-[820] duration-500 fade-in font-casual slide-in-from-bottom-2 md:text-4xl"
                    data-test="headline"
                >
                    {{ headline }}
                </h1>
                <p
                    v-if="booking.status === 'cancelled'"
                    class="mt-2 text-muted-foreground"
                    data-test="cancelled-note"
                >
                    {{
                        booking.cancelled_by === 'system'
                            ? "We couldn't take your deposit, so the booking didn't go through. You haven't been charged."
                            : booking.cancelled_by === 'business'
                              ? `${business.name} had to cancel it. Sorry for the trouble.`
                              : 'The time is free for someone else now.'
                    }}
                    <template
                        v-if="
                            booking.deposit > 0 &&
                            booking.cancelled_by !== 'system'
                        "
                    >
                        {{
                            booking.refund_due > 0
                                ? `Your ${formatMoney(booking.refund_due, booking.currency)} deposit ${booking.refunded ? 'has been refunded. It usually shows within 5 to 7 working days.' : 'is being refunded.'}`
                                : 'The deposit was kept, as it was inside the cancellation window.'
                        }}
                    </template>
                </p>
                <p
                    v-if="booking.status === 'confirmed'"
                    class="mt-2 text-muted-foreground"
                >
                    See you {{ date }} at
                    <span class="font-time">{{
                        timeIn(booking.starts_at, zone)
                    }}</span
                    >.
                </p>
            </div>

            <section
                class="mt-8 animate-in rounded-2xl border bg-card p-5 delay-150 duration-500 fill-mode-both fade-in slide-in-from-bottom-3"
                data-test="booking-card"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-lg font-semibold">
                            {{ booking.service_name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ duration(length) }}
                            <template v-if="booking.staff_name"
                                >with {{ booking.staff_name }}</template
                            >
                        </p>
                    </div>
                    <p
                        class="shrink-0 rounded-lg bg-muted px-2 py-1 text-xs font-medium font-time"
                        :title="`Booking reference ${booking.code}`"
                    >
                        {{ booking.code }}
                    </p>
                </div>

                <div
                    class="mt-4 flex items-start gap-3 rounded-xl bg-muted px-3 py-2.5 text-sm"
                >
                    <CalendarClock
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <div>
                        <p class="font-medium">{{ date }}</p>
                        <p class="font-time">
                            {{ timeIn(booking.starts_at, zone) }} –
                            {{ timeIn(booking.ends_at, zone) }}
                            <span class="text-muted-foreground"
                                >· {{ zoneCity(zone) }}</span
                            >
                        </p>
                        <p
                            v-if="!sameZone"
                            class="text-xs text-muted-foreground"
                        >
                            That’s
                            {{ timeIn(booking.starts_at, business.timezone) }}
                            in {{ zoneCity(business.timezone) }}.
                        </p>
                    </div>
                </div>

                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Price</dt>
                        <dd class="font-time">
                            {{ formatMoney(booking.price, booking.currency) }}
                        </dd>
                    </div>
                    <div v-if="booking.deposit" class="flex justify-between">
                        <dt class="text-muted-foreground">Deposit</dt>
                        <dd class="font-time">
                            {{ formatMoney(booking.deposit, booking.currency) }}
                        </dd>
                    </div>
                    <div class="flex justify-between font-medium">
                        <dt>To pay on the day</dt>
                        <dd class="font-time">
                            {{
                                formatMoney(
                                    booking.price -
                                        (booking.status === 'confirmed'
                                            ? booking.deposit
                                            : 0),
                                    booking.currency,
                                )
                            }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="business.address || business.phone"
                    class="mt-4 space-y-2 border-t pt-4 text-sm"
                    data-test="where"
                >
                    <a
                        v-if="business.address"
                        :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(business.address)}`"
                        target="_blank"
                        rel="noopener"
                        class="flex gap-2 underline-offset-4 hover:underline"
                    >
                        <MapPin class="mt-0.5 size-4 shrink-0" />
                        <span
                            >{{ business.address }}
                            <span class="text-muted-foreground"
                                >· Directions</span
                            ></span
                        >
                    </a>
                    <a
                        v-if="business.phone"
                        :href="`tel:${business.phone.replace(/[^0-9+]/g, '')}`"
                        class="flex gap-2 underline-offset-4 hover:underline"
                    >
                        <Phone class="mt-0.5 size-4 shrink-0" />
                        {{ business.phone }}
                    </a>
                </div>

                <p
                    v-if="
                        booking.customer_email && booking.status === 'confirmed'
                    "
                    class="mt-4 flex items-center gap-2 border-t pt-4 text-sm text-muted-foreground"
                >
                    <Mail class="size-4 shrink-0" />
                    We've emailed the details to {{ booking.customer_email }}.
                </p>
            </section>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <Button v-if="booking.calendar_url" as-child class="rounded-xl">
                    <a :href="booking.calendar_url" data-test="add-to-calendar">
                        <CalendarPlus class="size-4" />
                        Add to calendar
                    </a>
                </Button>
                <Button as-child variant="outline" class="rounded-xl">
                    <Link
                        :href="
                            route('booking.show', {
                                business: business.slug,
                            })
                        "
                        >Book something else</Link
                    >
                </Button>
            </div>

            <div
                v-if="cancellation"
                class="mt-10 border-t pt-6 text-center text-sm text-muted-foreground"
            >
                <p v-if="booking.deposit > 0 && cancellation.refund > 0">
                    Free to cancel until {{ freeUntil }}.
                </p>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="ghost"
                            class="mt-1 text-muted-foreground"
                            data-test="cancel-open"
                            >Cancel this booking</Button
                        >
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Cancel your booking?</DialogTitle>
                            <DialogDescription>
                                {{ booking.service_name }} on {{ date }} at
                                {{ timeIn(booking.starts_at, zone) }}.
                                {{ cancelOutcome }}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="outline">Keep it</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="cancelling"
                                data-test="cancel-confirm"
                                @click="cancel"
                            >
                                <Spinner v-if="cancelling" />
                                Cancel booking
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
            <InputError
                class="mt-4 text-center"
                :message="cancelError ?? undefined"
            />
        </main>
    </div>
</template>

<style scoped>
.booked-check circle {
    transform-origin: center;
    animation: pop 420ms cubic-bezier(0.2, 0.9, 0.3, 1.3);
}

.booked-check path {
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    animation: tick 360ms 220ms ease-out forwards;
}

@keyframes pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
}

@keyframes tick {
    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .booked-check circle,
    .booked-check path {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
