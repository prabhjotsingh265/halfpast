<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, Pencil, TimerOff } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BookingHeader from '@/components/booking/BookingHeader.vue';
import DetailsForm from '@/components/booking/DetailsForm.vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import { Button } from '@/components/ui/button';
import { timeIn, zoneCity } from '@/lib/booking';
import { formatMoney } from '@/lib/money';
import { countdown, duration } from '@/lib/time';
import { localZone } from '@/lib/zones';
import type { CheckoutBooking, CheckoutCustomer } from '@/types/booking';

const props = defineProps<{
    booking: CheckoutBooking;
    customer: CheckoutCustomer | null;
    holdMinutes: number;
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

onBeforeUnmount(() => window.clearInterval(timer));

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
                        class="flex items-center gap-3 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        <CreditCard class="size-5 shrink-0" />
                        Payment options appear here once {{ business.name }}
                        switches them on.
                    </div>
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
