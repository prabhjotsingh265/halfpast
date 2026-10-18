<script setup lang="ts">
import { PPM } from '@/composables/useTimeScale';

/**
 * The hours down the left of a calendar grid, with a dot where now is.
 */
const props = defineProps<{
    range: [number, number];
    marks: number[];
    now: number | null;
}>();

const top = (minutes: number) => (minutes - props.range[0]) * PPM;

const hourLabel = new Intl.DateTimeFormat(undefined, { hour: 'numeric' });
const labelFor = (minutes: number) =>
    hourLabel.format(new Date(2000, 0, 1, Math.floor(minutes / 60)));
</script>

<template>
    <div
        class="sticky left-0 z-10 bg-card"
        :style="{ height: `${(range[1] - range[0]) * PPM}px` }"
    >
        <span
            v-for="mark in marks"
            :key="mark"
            class="absolute right-2 -translate-y-1/2 text-[10px] text-muted-foreground tabular-nums"
            :style="{ top: `${top(mark)}px` }"
        >
            {{ mark < 24 * 60 ? labelFor(mark) : '' }}
        </span>
        <span
            v-if="now !== null"
            class="absolute right-0 size-2 translate-x-1/2 -translate-y-1/2 rounded-full bg-destructive"
            :style="{ top: `${top(now)}px` }"
            data-test="now-dot"
        />
    </div>
</template>
