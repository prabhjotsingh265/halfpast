<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { daylight } from '@/lib/daylight';
import { hourNow } from '@/lib/time';

/**
 * A 24-hour clock face drawn from the visitor's own time: the part of today
 * that has gone is filled in, and the working day sits on the outer ring as
 * a few bookings. Midnight is at the top.
 */

const SIZE = 600;
const C = SIZE / 2;

const hour = ref(hourNow());
const drawn = ref(false);
const isDark = ref(false);

const angle = (h: number) => (h / 24) * 360 - 90;

function point(h: number, r: number): [number, number] {
    const a = (angle(h) * Math.PI) / 180;

    return [C + r * Math.cos(a), C + r * Math.sin(a)];
}

function arc(from: number, to: number, r: number): string {
    const [x1, y1] = point(from, r);
    const [x2, y2] = point(to, r);
    const large = ((to - from + 24) % 24) / 24 > 0.5 ? 1 : 0;

    return `M ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2}`;
}

const ticks = Array.from({ length: 48 }, (_, i) => {
    const h = i / 2;
    const major = i % 2 === 0;
    const [x1, y1] = point(h, major ? 262 : 268);
    const [x2, y2] = point(h, 276);

    return { x1, y1, x2, y2, major };
});

const labels = [0, 6, 12, 18].map((h) => {
    const [x, y] = point(h, 238);

    return { h, x, y, text: `${String(h).padStart(2, '0')}:00` };
});

const bookings = [
    [9, 11],
    [11.5, 12.5],
    [13, 14.5],
    [15.5, 16],
    [16.5, 18],
].map(([from, to]) => ({ from, to, d: arc(from, to, 290) }));

// The filled wedge is a circle stroked as thick as its radius, so its dash
// length is the share of the day that has passed.
const passed = computed(() => (drawn.value ? (hour.value / 24) * 100 : 0));
const hand = computed(() => point(hour.value, 250));

let timer = 0;

onMounted(async () => {
    isDark.value = document.documentElement.classList.contains('dark');
    await nextTick();
    requestAnimationFrame(() => (drawn.value = true));
    timer = window.setInterval(() => (hour.value = hourNow()), 30_000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <svg :viewBox="`0 0 ${SIZE} ${SIZE}`" aria-hidden="true" class="dial">
        <circle
            :cx="C"
            :cy="C"
            r="276"
            fill="none"
            stroke="currentColor"
            stroke-opacity="0.12"
        />
        <circle
            :cx="C"
            :cy="C"
            r="290"
            fill="none"
            stroke="currentColor"
            stroke-opacity="0.06"
            stroke-width="14"
        />

        <circle
            class="wedge"
            :cx="C"
            :cy="C"
            r="120"
            fill="none"
            stroke="var(--sun)"
            stroke-opacity="0.16"
            stroke-width="240"
            pathLength="100"
            :stroke-dasharray="`${passed} 100`"
            :transform="`rotate(-90 ${C} ${C})`"
        />

        <line
            v-for="(tick, i) in ticks"
            :key="i"
            :x1="tick.x1"
            :y1="tick.y1"
            :x2="tick.x2"
            :y2="tick.y2"
            stroke="currentColor"
            :stroke-opacity="tick.major ? 0.35 : 0.15"
            :stroke-width="tick.major ? 2 : 1"
        />

        <text
            v-for="label in labels"
            :key="label.h"
            :x="label.x"
            :y="label.y"
            text-anchor="middle"
            dominant-baseline="middle"
            class="dial-label"
        >
            {{ label.text }}
        </text>

        <path
            v-for="booking in bookings"
            :key="booking.from"
            :d="booking.d"
            fill="none"
            :stroke="
                daylight(
                    (booking.from + booking.to) / 2,
                    isDark ? 'dark' : 'light',
                )
            "
            stroke-width="14"
            stroke-linecap="round"
            class="booking-arc"
        />

        <line
            :x1="C"
            :y1="C"
            :x2="hand[0]"
            :y2="hand[1]"
            stroke="var(--sun)"
            stroke-width="3"
            stroke-linecap="round"
        />
        <circle :cx="C" :cy="C" r="7" fill="var(--sun)" />
        <circle :cx="hand[0]" :cy="hand[1]" r="5" fill="var(--sun)" />
    </svg>
</template>

<style scoped>
.wedge {
    transition: stroke-dasharray 1600ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.dial-label {
    font-size: 15px;
    fill: currentColor;
    fill-opacity: 0.45;
    --mono: 1;
}

.booking-arc {
    opacity: 0.9;
}

@media (prefers-reduced-motion: reduce) {
    .wedge {
        transition: none;
    }
}
</style>
