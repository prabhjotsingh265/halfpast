<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Currency } from '@/lib/marketing';
import { guessCurrency, money } from '@/lib/marketing';

const perWeek = ref(60);
const noShowRate = ref(8);
const price = ref(40);
const depositShare = ref(20);
const currency = ref<Currency>('GBP');

onMounted(() => (currency.value = guessCurrency()));

const WEEKS_PER_MONTH = 52 / 12;

const noShows = computed(
    () => perWeek.value * (noShowRate.value / 100) * WEEKS_PER_MONTH,
);
const lost = computed(() => noShows.value * price.value);
// Each no-show forfeits its deposit, so that share of the price comes back.
const kept = computed(() => lost.value * (depositShare.value / 100));

// Numbers glide to their new value instead of jumping.
const shown = ref({
    noShows: noShows.value,
    lost: lost.value,
    kept: kept.value,
});
let frame = 0;

watch([noShows, lost, kept], ([n, l, k]) => {
    cancelAnimationFrame(frame);
    const from = { ...shown.value };
    const start = performance.now();

    const step = (time: number) => {
        const t = Math.min(1, (time - start) / 350);
        const eased = 1 - (1 - t) ** 3;
        shown.value = {
            noShows: from.noShows + (n - from.noShows) * eased,
            lost: from.lost + (l - from.lost) * eased,
            kept: from.kept + (k - from.kept) * eased,
        };

        if (t < 1) {
            frame = requestAnimationFrame(step);
        }
    };

    frame = requestAnimationFrame(step);
});

onBeforeUnmount(() => cancelAnimationFrame(frame));

const sliders = computed(() => [
    {
        id: 'per-week',
        label: 'Appointments a week',
        model: perWeek,
        min: 10,
        max: 300,
        step: 5,
        display: `${perWeek.value}`,
    },
    {
        id: 'no-show',
        label: "Customers who don't turn up",
        model: noShowRate,
        min: 0,
        max: 25,
        step: 1,
        display: `${noShowRate.value}%`,
    },
    {
        id: 'price',
        label: 'Average price',
        model: price,
        min: 10,
        max: 200,
        step: 5,
        display: money(price.value, currency.value),
    },
    {
        id: 'deposit',
        label: 'Deposit you ask for',
        model: depositShare,
        min: 10,
        max: 50,
        step: 5,
        display: `${depositShare.value}%`,
    },
]);
</script>

<template>
    <div class="grid gap-10 lg:grid-cols-[1fr_1.1fr]">
        <div class="space-y-6">
            <div v-for="slider in sliders" :key="slider.id">
                <div class="flex items-baseline justify-between gap-4">
                    <label :for="slider.id" class="text-sm font-semibold">{{
                        slider.label
                    }}</label>
                    <span class="text-sm font-time">{{ slider.display }}</span>
                </div>
                <input
                    :id="slider.id"
                    v-model.number="slider.model.value"
                    type="range"
                    :min="slider.min"
                    :max="slider.max"
                    :step="slider.step"
                    class="mt-2 w-full accent-[var(--sun-deep)]"
                />
            </div>
        </div>

        <div class="rounded-2xl bg-dusk p-6 text-white md:p-8">
            <p class="text-sm text-white/70">
                Every month, no-shows cost you about
            </p>
            <p
                class="mt-1 text-5xl font-[650] font-time md:text-6xl"
                aria-live="polite"
            >
                {{ money(shown.lost, currency) }}
            </p>
            <p class="mt-2 text-sm text-white/70">
                That's
                <span class="font-time">{{ Math.round(shown.noShows) }}</span>
                empty chairs a month.
            </p>

            <div
                class="mt-8 h-3 overflow-hidden rounded-full bg-white/15"
                aria-hidden="true"
            >
                <div
                    class="h-full rounded-full bg-sun transition-[width] duration-300"
                    :style="{ width: `${depositShare}%` }"
                />
            </div>
            <p class="mt-4 text-sm text-white/70">
                With a {{ depositShare }}% deposit, you keep
            </p>
            <p class="mt-1 text-3xl font-[650] text-sun font-time md:text-4xl">
                {{ money(shown.kept, currency) }} a month
            </p>
            <p class="mt-6 text-xs text-white/60">
                Simple maths: every no-show forfeits its deposit. What you
                actually keep depends on your cancellation rules.
            </p>
        </div>
    </div>
</template>
