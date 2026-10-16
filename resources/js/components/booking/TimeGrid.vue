<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { daylight } from '@/lib/daylight';
import type { DayPart } from '@/lib/booking';
import { dayPart, hourIn, timeIn } from '@/lib/booking';
import type { BookableTime } from '@/types/booking';

/**
 * The free times on a day, grouped into morning, afternoon and evening,
 * each tinted by the hour it starts at.
 */
const props = defineProps<{
    times: BookableTime[];
    zone: string;
    selected: string | null;
    /** Changes whenever a different day is shown, to replay the entrance. */
    dayKey: string;
}>();

const emit = defineEmits<{ select: [startsAt: string] }>();

const groups = computed(() => {
    const parts = new Map<DayPart, { time: BookableTime; hour: number }[]>();

    for (const time of props.times) {
        const hour = hourIn(time.starts_at, props.zone);
        const part = dayPart(hour);

        parts.set(part, [...(parts.get(part) ?? []), { time, hour }]);
    }

    return [...parts.entries()].map(([part, times]) => ({ part, times }));
});
</script>

<template>
    <div class="space-y-5">
        <div v-for="group in groups" :key="`${dayKey}-${group.part}`">
            <p
                class="mb-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ group.part }}
            </p>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                <button
                    v-for="({ time, hour }, index) in group.times"
                    :key="`${dayKey}-${time.starts_at}`"
                    type="button"
                    class="slot relative h-11 animate-in rounded-xl border bg-[var(--tint)] text-sm font-semibold fade-in slide-in-from-bottom-1 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none dark:bg-[var(--tint-dark)]"
                    :class="selected === time.starts_at && 'is-selected'"
                    :style="{
                        '--tint': daylight(hour),
                        '--tint-dark': daylight(hour, 'dark'),
                        animationDelay: `${Math.min(index, 12) * 18}ms`,
                    }"
                    :aria-pressed="selected === time.starts_at"
                    :data-test="`time-${time.starts_at}`"
                    @click="emit('select', time.starts_at)"
                >
                    <span class="slot-time">{{
                        timeIn(time.starts_at, zone)
                    }}</span>
                    <span
                        v-if="selected === time.starts_at"
                        class="absolute -top-1.5 -right-1.5 grid size-5 animate-in place-items-center rounded-full bg-foreground text-background zoom-in-50"
                        aria-hidden="true"
                    >
                        <Check class="size-3" />
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slot {
    border-color: color-mix(in oklch, var(--ink) 10%, transparent);
    transition:
        transform 140ms ease-out,
        box-shadow 200ms ease-out;
}

.slot:hover {
    box-shadow: 0 6px 16px -8px color-mix(in oklch, var(--ink) 35%, transparent);
}

.slot:active {
    transform: scale(0.97);
}

.slot.is-selected {
    box-shadow:
        0 0 0 2px var(--background),
        0 0 0 4px var(--ink);
}

.slot-time {
    display: inline-block;
    transition: --mono 450ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.slot.is-selected .slot-time {
    --mono: 1;
}

@media (prefers-reduced-motion: reduce) {
    .slot,
    .slot-time {
        transition: none;
    }
}
</style>
