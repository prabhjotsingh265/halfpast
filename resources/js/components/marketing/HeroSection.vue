<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BookingPhone from '@/components/marketing/BookingPhone.vue';
import HeroDial from '@/components/marketing/HeroDial.vue';
import { Button } from '@/components/ui/button';
import { daylight } from '@/lib/daylight';
import type { Currency } from '@/lib/marketing';
import { businessTypes, guessCurrency } from '@/lib/marketing';
import { clock12, HALF_PAST_THREE, hourNow } from '@/lib/time';

const name = ref('');
const typeIndex = ref(0);
const currency = ref<Currency>('GBP');
// Half past three until the page is running and knows the visitor's clock.
const hour = ref(HALF_PAST_THREE);
const clockKnown = ref(false);
const isDark = ref(false);

const type = computed(() => businessTypes[typeIndex.value]);
const businessName = computed(
    () => name.value.trim() || type.value.placeholder,
);

// The glow behind the phone is the colour of the visitor's current hour, and
// sits where the sun would be: low on the left in the morning, high at noon.
const glow = computed(() => {
    const t = Math.min(1, Math.max(0, (hour.value - 6) / 15));
    const x = 15 + t * 70;
    const y = 30 - Math.sin(t * Math.PI) * 20;
    const tint = daylight(hour.value, isDark.value ? 'dark' : 'light');

    return `radial-gradient(60rem 40rem at ${x}% ${y}%, ${tint}, transparent 70%)`;
});

function start(): void {
    router.visit(route('register', { business: businessName.value }));
}

let timer = 0;

onMounted(() => {
    currency.value = guessCurrency();
    isDark.value = document.documentElement.classList.contains('dark');
    hour.value = hourNow();
    clockKnown.value = true;
    timer = window.setInterval(() => (hour.value = hourNow()), 30_000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <section
        class="relative isolate overflow-clip"
        :style="{ backgroundImage: glow }"
    >
        <HeroDial
            class="pointer-events-none absolute top-1/2 -right-40 -z-10 hidden w-[54rem] max-w-none -translate-y-[45%] text-foreground opacity-60 lg:block"
        />

        <div
            class="mx-auto grid max-w-6xl items-center gap-14 px-5 pt-28 pb-20 md:px-8 lg:grid-cols-[1.3fr_0.7fr] lg:pt-36 lg:pb-28"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    <span
                        class="mr-2 inline-block size-2 rounded-full bg-sun align-middle"
                        aria-hidden="true"
                    /><span
                        v-if="clockKnown"
                        class="animate-in duration-500 fade-in"
                        >It's
                        <span class="whitespace-nowrap font-time">{{
                            clock12(hour)
                        }}</span>
                        where you are. </span
                    >Someone's looking for a time.
                </p>

                <h1
                    class="mt-5 text-[clamp(3rem,7.5vw,5.75rem)] leading-[0.92] font-[830] tracking-tight font-casual"
                >
                    Bookings that<br />show up.
                </h1>

                <p
                    class="mt-6 max-w-xl text-lg text-muted-foreground md:text-xl"
                >
                    A booking page for your salon, clinic or classes. It takes
                    deposits, sends the reminders, and never double-books, even
                    when two people tap the same time at once.
                </p>

                <form
                    class="mt-9 max-w-xl rounded-2xl border bg-background/80 p-4 shadow-sm backdrop-blur"
                    @submit.prevent="start"
                >
                    <label for="business-name" class="text-sm font-semibold">
                        Type your business name. Your page builds itself on the
                        phone.
                    </label>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input
                            id="business-name"
                            v-model="name"
                            type="text"
                            maxlength="40"
                            autocomplete="organization"
                            :placeholder="type.placeholder"
                            class="h-11 w-full min-w-0 rounded-lg border bg-background px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 sm:flex-1"
                        />
                        <Button type="submit" size="lg" class="h-11">
                            Create my page
                            <ArrowRight class="size-4" />
                        </Button>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap gap-2"
                        role="radiogroup"
                        aria-label="Kind of business"
                    >
                        <button
                            v-for="(item, index) in businessTypes"
                            :key="item.kind"
                            type="button"
                            role="radio"
                            :aria-checked="typeIndex === index"
                            class="rounded-full border px-3 py-1 text-sm transition-colors"
                            :class="
                                typeIndex === index
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'hover:bg-accent'
                            "
                            @click="typeIndex = index"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                </form>

                <p class="mt-4 text-sm text-muted-foreground">
                    Free while in beta. Stripe, PayPal and Razorpay supported.
                </p>
            </div>

            <div class="relative">
                <BookingPhone
                    :name="businessName"
                    :type="type"
                    :currency="currency"
                />
            </div>
        </div>
    </section>
</template>
