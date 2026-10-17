<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleCheck,
    CreditCard,
    Loader,
    Pencil,
    TimerOff,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BookingHeader from '@/components/booking/BookingHeader.vue';
import DetailsForm from '@/components/booking/DetailsForm.vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { timeIn, zoneCity } from '@/lib/booking';
import { formatMoney } from '@/lib/money';
import { countdown, duration } from '@/lib/time';
import { localZone } from '@/lib/zones';
import type {
    CheckoutBooking,
    CheckoutCustomer,
    CheckoutPayment,
} from '@/types/booking';

const props = defineProps<{
    booking: CheckoutBooking;
    customer: CheckoutCustomer | null;
    gateways: { key: string; label: string }[];
    payment: CheckoutPayment | null;
    cancelled: boolean;
    returned: boolean;
    holdMinutes: number;
    paymentWindow: number;
    serverNow: string;
}>();

const business = computed(() => usePage().props.business!);

// Count down from the server's clock, in case the browser's is off.
const skew = new Date(props.serverNow).getTime() - Date.now();
const now = ref(Date.now() + skew);
let timer = 0;

onMounted(() => {
    timer = window.setInterval(() => (now.value = Date.now() + skew), 250);
});

onBeforeUnmount(() => {
    window.clearInterval(timer);
    window.clearTimeout(poll);
});

const page = usePage();
const paymentError = computed(() => page.props.errors?.payment ?? null);
const startingWith = ref<string | null>(null);

/** One message about the last payment, if there's something to say. */
const notice = computed<{
    tone: 'good' | 'warn' | 'wait';
    text: string;
} | null>(() => {
    const payment = props.payment;
    const amount = formatMoney(props.booking.deposit, props.booking.currency);

    if (
        payment?.outcome === 'slot_taken' ||
        payment?.outcome === 'not_bookable'
    ) {
        return {
            tone: 'good',
            text: payment.refunded
                ? `Your ${amount} is being refunded in full.`
                : `You haven't been charged. The ${amount} was only reserved and will be released.`,
        };
    }

    if (payment?.status === 'failed' && payment.failure_reason === 'declined') {
        return {
            tone: 'warn',
            text: "Your payment didn't go through, and nothing was taken. Try again, or choose another way to pay.",
        };
    }

    if (payment?.status === 'pending' && props.returned) {
        return {
            tone: 'wait',
            text: "Checking your payment… If you've paid, this updates by itself in a moment.",
        };
    }

    if (props.cancelled) {
        return {
            tone: 'warn',
            text: 'You came back without paying. Your time is still held.',
        };
    }

    return null;
});

// While a payment is being confirmed (UPI can take a little while), look
// again every few seconds, for up to two minutes.
let poll = 0;
let polls = 0;

function watchPayment(): void {
    if (props.payment?.status !== 'pending' || !props.returned || polls >= 40) {
        return;
    }

    poll = window.setTimeout(() => {
        polls += 1;
        router.reload({
            only: ['booking', 'payment'],
            onFinish: watchPayment,
        });
    }, 3000);
}

onMounted(watchPayment);

function pay(gateway: string): void {
    startingWith.value = gateway;
    router.post(
        route('booking.pay', {
            business: business.value.slug,
            booking: props.booking.code,
        }),
        { gateway },
        {
            preserveScroll: true,
            onFinish: () => (startingWith.value = null),
        },
    );
}

const secondsLeft = computed(() =>
    props.booking.hold_expires_at
        ? Math.max(
              0,
              (new Date(props.booking.hold_expires_at).getTime() - now.value) /
                  1000,
          )
        : 0,
);
const held = computed(() => props.booking.held && secondsLeft.value > 0);
// The hold is up, but nobody has taken the time, so it can still be booked.
const lapsedButFree = computed(() => !held.value && props.booking.renewable);
const canFinish = computed(() => held.value || lapsedButFree.value);
// Details saved and a deposit to pay: the payment step.
const editingDetails = ref(false);
const paying = computed(
    () =>
        props.booking.deposit > 0 &&
        props.customer !== null &&
        !editingDetails.value,
);
const progress = computed(() => secondsLeft.value / (props.holdMinutes * 60));
// Screen readers hear the time left once a minute, not every second.
const minutesLeft = computed(() => Math.ceil(secondsLeft.value / 60));

const zone = localZone();
const date = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: zone,
    }).format(new Date(props.booking.starts_at)),
);
const length = computed(
    () =>
        (new Date(props.booking.ends_at).getTime() -
            new Date(props.booking.starts_at).getTime()) /
        60_000,
);

function release(): void {
    router.delete(
        route('booking.release', {
            business: business.value.slug,
            booking: props.booking.code,
        }),
    );
}

const againUrl = computed(() =>
    route('booking.show', {
        business: business.value.slug,
        service: props.booking.service_id ?? undefined,
    }),
);
</script>

<template>
    <Head :title="`Your booking with ${business.name}`" />

    <div class="min-h-svh bg-background pb-16 text-foreground">
        <BookingHeader compact />

        <main
            class="mx-auto grid max-w-5xl gap-8 px-4 py-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
        >
            <div class="min-w-0 space-y-8">
                <div
                    v-if="notice"
                    class="flex animate-in items-start gap-3 rounded-2xl border p-4 text-sm fade-in"
                    :class="{
                        'border-success/30 bg-success/10':
                            notice.tone === 'good',
                        'border-sun-deep/30 bg-sun/10': notice.tone === 'warn',
                        'bg-muted': notice.tone === 'wait',
                    }"
                    role="status"
                    data-test="payment-notice"
                >
                    <CircleCheck
                        v-if="notice.tone === 'good'"
                        class="mt-0.5 size-4 shrink-0 text-success"
                    />
                    <TriangleAlert
                        v-else-if="notice.tone === 'warn'"
                        class="mt-0.5 size-4 shrink-0 text-sun-deep"
                    />
                    <Loader
                        v-else
                        class="mt-0.5 size-4 shrink-0 animate-spin text-muted-foreground"
                    />
                    <p>{{ notice.text }}</p>
                </div>

                <button
                    v-if="held"
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    data-test="release"
                    @click="release"
                >
                    <ArrowLeft class="size-4" />
                    Choose a different time
                </button>

                <section
                    v-if="held"
                    class="flex animate-in items-center gap-5 rounded-2xl border bg-card p-5 fade-in slide-in-from-bottom-2"
                    data-test="hold-card"
                >
                    <div
                        class="relative grid h-16 w-28 shrink-0 place-items-center rounded-[14px] bg-sun/20"
                    >
                        <span class="text-lg font-semibold font-time">{{
                            timeIn(booking.starts_at, zone)
                        }}</span>
                        <HoldRing
                            :progress="progress"
                            :offset="5"
                            :radius="14"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold">Held for you</p>
                        <p class="text-sm text-muted-foreground">
                            <span
                                class="font-medium text-foreground font-time"
                                data-test="countdown"
                                >{{ countdown(secondsLeft) }}</span
                            >
                            left to finish booking. After that the time goes
                            back on the list.
                        </p>
                        <p class="sr-only" aria-live="polite">
                            {{ minutesLeft }}
                            {{ minutesLeft === 1 ? 'minute' : 'minutes' }} left
                        </p>
                    </div>
                </section>

                <section
                    v-else-if="lapsedButFree"
                    class="flex animate-in items-start gap-3 rounded-2xl border border-sun-deep/30 bg-sun/10 p-4 text-sm fade-in"
                    data-test="hold-lapsed"
                >
                    <TimerOff class="mt-0.5 size-4 shrink-0 text-sun-deep" />
                    <p>
                        <span class="font-medium"
                            >Your {{ holdMinutes }} minutes are up</span
                        >, but nobody has taken
                        {{ timeIn(booking.starts_at, zone) }} yet. Finish now
                        and it's yours.
                    </p>
                </section>

                <section
                    v-else
                    class="flex animate-in flex-col items-center rounded-2xl border border-dashed px-6 py-12 text-center fade-in"
                    data-test="hold-expired"
                >
                    <span
                        class="grid size-12 place-items-center rounded-full bg-muted"
                    >
                        <TimerOff class="size-5 text-muted-foreground" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold">
                        Someone else has this time now
                    </h2>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        {{ timeIn(booking.starts_at, zone) }} on {{ date }} has
                        gone to someone else. There may be other times that suit
                        you.
                    </p>
                    <Button as-child class="mt-6 rounded-xl">
                        <Link :href="againUrl">Pick a time again</Link>
                    </Button>
                </section>

                <DetailsForm
                    v-if="canFinish && !paying"
                    :booking="booking"
                    :customer="customer"
                />

                <section
                    v-else-if="canFinish && paying && customer"
                    class="animate-in space-y-5 rounded-2xl border bg-card p-5 fade-in slide-in-from-bottom-2 md:p-6"
                    data-test="payment-step"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">
                                Pay the deposit
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    formatMoney(
                                        booking.deposit,
                                        booking.currency,
                                    )
                                }}
                                now keeps your booking. The rest is paid on the
                                day.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            @click="editingDetails = true"
                        >
                            <Pencil class="size-3.5" />
                            Edit details
                        </button>
                    </div>
                    <p class="rounded-xl bg-muted px-3 py-2.5 text-sm">
                        {{ customer.name }} · {{ customer.email }}
                        <template v-if="customer.phone">
                            · {{ customer.phone }}</template
                        >
                    </p>
                    <div
                        v-if="gateways.length === 0"
                        class="flex items-center gap-3 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        <CreditCard class="size-5 shrink-0" />
                        Online payment isn't available right now. Please contact
                        {{ business.name }} to book.
                    </div>
                    <div v-else class="grid gap-2" data-test="gateways">
                        <Button
                            v-for="gateway in gateways"
                            :key="gateway.key"
                            type="button"
                            size="lg"
                            class="w-full rounded-xl"
                            :disabled="startingWith !== null"
                            :data-test="`pay-${gateway.key}`"
                            @click="pay(gateway.key)"
                        >
                            <Spinner v-if="startingWith === gateway.key" />
                            Pay
                            {{ formatMoney(booking.deposit, booking.currency) }}
                            · {{ gateway.label }}
                        </Button>
                        <p class="text-center text-xs text-muted-foreground">
                            The money is only taken once your booking is
                            confirmed. Starting a payment gives you a fresh
                            {{ paymentWindow }} minutes.
                        </p>
                    </div>
                    <InputError :message="paymentError ?? undefined" />
                </section>
            </div>

            <aside
                class="rounded-2xl border bg-card p-5 lg:sticky lg:top-6"
                aria-label="Your booking"
            >
                <p
                    class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Your booking
                </p>
                <p class="mt-3 text-lg font-semibold">
                    {{ booking.service_name }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ duration(length) }}
                    <template v-if="booking.staff_name"
                        >with {{ booking.staff_name }}</template
                    >
                </p>
                <div class="mt-4 rounded-xl bg-muted px-3 py-2.5 text-sm">
                    <p class="font-medium">{{ date }}</p>
                    <p class="font-time">
                        {{ timeIn(booking.starts_at, zone) }} –
                        {{ timeIn(booking.ends_at, zone) }}
                        <span class="text-muted-foreground"
                            >· {{ zoneCity(zone) }}</span
                        >
                    </p>
                </div>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Price</dt>
                        <dd class="font-time">
                            {{ formatMoney(booking.price, booking.currency) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">To pay now</dt>
                        <dd class="font-semibold font-time">
                            {{
                                booking.deposit
                                    ? formatMoney(
                                          booking.deposit,
                                          booking.currency,
                                      )
                                    : 'Nothing'
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="booking.deposit"
                        class="flex justify-between text-muted-foreground"
                    >
                        <dt>On the day</dt>
                        <dd class="font-time">
                            {{
                                formatMoney(
                                    booking.price - booking.deposit,
                                    booking.currency,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </aside>
        </main>
    </div>
</template>
