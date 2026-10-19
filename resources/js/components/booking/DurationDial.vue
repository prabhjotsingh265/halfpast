<script setup lang="ts">
import { computed } from 'vue';

/**
 * How long something takes, drawn as time on a clock face: 45 minutes is
 * three quarters of the way round. Past an hour it carries on round an
 * inner ring, and past two hours round a third.
 */
const props = defineProps<{
    minutes: number;
    /** Drawn in the sun colour when picked; ink otherwise. */
    active?: boolean;
}>();

const RINGS = [17, 11.5, 6];

const rings = computed(() =>
    RINGS.map((radius, lap) => {
        const share = Math.min(1, Math.max(0, (props.minutes - lap * 60) / 60));
        const circumference = 2 * Math.PI * radius;

        return {
            radius,
            share,
            circumference: circumference.toFixed(2),
            length: (circumference * share).toFixed(2),
            delay: `${lap * 220}ms`,
        };
    }).filter((ring, lap) => lap === 0 || ring.share > 0),
);
</script>

<template>
    <svg
        viewBox="0 0 44 44"
        class="size-11 shrink-0 -rotate-90"
        :class="active ? 'text-sun' : 'text-foreground/70'"
        aria-hidden="true"
    >
        <circle
            v-for="ring in rings"
            :key="`track-${ring.radius}`"
            cx="22"
            cy="22"
            :r="ring.radius"
            fill="none"
            class="stroke-foreground/10"
            stroke-width="3.5"
        />
        <circle
            v-for="ring in rings"
            :key="`arc-${ring.radius}-${active}`"
            cx="22"
            cy="22"
            :r="ring.radius"
            fill="none"
            stroke="currentColor"
            stroke-width="3.5"
            stroke-linecap="round"
            class="dial-arc transition-colors duration-300"
            :style="{
                strokeDasharray: `${ring.length} ${ring.circumference}`,
                '--c': ring.circumference,
                animationDelay: ring.delay,
            }"
        />
    </svg>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .dial-arc {
        animation: dial-draw 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes dial-draw {
    from {
        stroke-dasharray: 0 var(--c);
    }
}
</style>
