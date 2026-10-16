<script setup lang="ts">
import { computed } from 'vue';
import { daylightRange } from '@/lib/daylight';
import type { Week } from '@/lib/week';
import { clockLabel, WEEKDAYS } from '@/lib/week';

/**
 * A small picture of someone's week: one column per day, filled where they
 * work, from 6 am at the top to 10 pm at the bottom.
 */
const props = defineProps<{ week: Week }>();

const FROM = 6 * 60;
const TO = 22 * 60;

const days = computed(() =>
    WEEKDAYS.map((day, index) => {
        const stretches = props.week[index];

        return {
            ...day,
            off: stretches.length === 0,
            title:
                stretches.length === 0
                    ? `${day.long}: off`
                    : `${day.long}: ${stretches
                          .map(
                              (s) =>
                                  `${clockLabel(s.start)}–${clockLabel(s.end)}`,
                          )
                          .join(', ')}`,
            blocks: stretches.map((s) => {
                const start = Math.max(FROM, Math.min(TO, s.start));
                const end = Math.max(start, Math.min(TO, s.end));

                return {
                    top: `${((start - FROM) / (TO - FROM)) * 100}%`,
                    height: `${((end - start) / (TO - FROM)) * 100}%`,
                    ...daylightRange(start / 60, end / 60),
                };
            }),
        };
    }),
);
</script>

<template>
    <ul class="grid grid-cols-7 gap-1" :aria-label="'Working week'">
        <li
            v-for="day in days"
            :key="day.iso"
            class="flex flex-col items-center gap-1"
            :title="day.title"
        >
            <span class="sr-only">{{ day.title }}</span>
            <span
                class="text-[10px] font-medium"
                :class="day.off ? 'text-muted-foreground/60' : ''"
                aria-hidden="true"
                >{{ day.letter }}</span
            >
            <span
                class="relative h-10 w-full overflow-hidden rounded bg-muted ring-1 ring-border ring-inset"
                aria-hidden="true"
            >
                <span
                    v-for="(block, index) in day.blocks"
                    :key="index"
                    class="absolute inset-x-0 bg-[linear-gradient(180deg,var(--from),var(--to))] dark:bg-[linear-gradient(180deg,var(--from-dark),var(--to-dark))]"
                    :style="block"
                />
            </span>
        </li>
    </ul>
</template>
