<script setup lang="ts">
import { computed } from 'vue';
import { daylightAccent } from '@/lib/daylight';
import type { WeekHours } from '@/lib/openingHours';
import {
    clockHours,
    clockLabel,
    localDay,
    weekdayNames,
} from '@/lib/openingHours';

/**
 * The week's opening hours drawn on one timeline: each day's stretches as
 * bars in the colours of the hours they cover, today picked out, and a
 * needle where the business's clock is now.
 */
const props = defineProps<{
    hours: WeekHours;
    zone: string;
    now: Date;
}>();

const names = computed(() => weekdayNames());

// From the earliest opening to the latest closing, to the whole hour.
const axis = computed(() => {
    const stretches = Object.values(props.hours).flat();

    if (stretches.length === 0) {
        return null;
    }

    const start = Math.floor(
        Math.min(...stretches.map(([from]) => clockHours(from))),
    );
    const end = Math.ceil(
        Math.max(...stretches.map(([, to]) => clockHours(to))),
    );

    return { start, end, span: Math.max(1, end - start) };
});

const position = (hour: number) =>
    axis.value ? ((hour - axis.value.start) / axis.value.span) * 100 : 0;

const today = computed(() => localDay(props.now, props.zone));

const ticks = computed(() => {
    if (!axis.value) {
        return [];
    }

    const every = axis.value.span > 10 ? 3 : 2;
    const first = Math.ceil(axis.value.start / every) * every;

    return Array.from(
        { length: Math.floor((axis.value.end - first) / every) + 1 },
        (_, i) => first + i * every,
    );
});

const days = computed(() =>
    names.value.map((name, index) => {
        const weekday = index + 1;

        return {
            weekday,
            name,
            isToday: weekday === today.value.weekday,
            bars: (props.hours[weekday] ?? []).map(([from, to]) => {
                const start = clockHours(from);
                const end = clockHours(to);

                return {
                    key: `${from}-${to}`,
                    label: `${clockLabel(from)}–${clockLabel(to)}`,
                    style: {
                        left: `${position(start)}%`,
                        width: `${position(end) - position(start)}%`,
                        '--from': daylightAccent(start),
                        '--to': daylightAccent(end),
                        '--from-dark': daylightAccent(start, 'dark'),
                        '--to-dark': daylightAccent(end, 'dark'),
                        animationDelay: `${120 + index * 55}ms`,
                    },
                };
            }),
        };
    }),
);

const needle = computed(() => {
    const hour = today.value.hour;

    return axis.value && hour >= axis.value.start && hour <= axis.value.end
        ? position(hour)
        : null;
});
</script>

<template>
    <div v-if="axis" class="text-xs">
        <!-- For screen readers: the same hours as a list. -->
        <ul class="sr-only">
            <li v-for="day in days" :key="day.weekday">
                {{ day.name }}:
                {{
                    day.bars.length
                        ? day.bars.map((bar) => bar.label).join(', ')
                        : 'closed'
                }}
            </li>
        </ul>

        <div aria-hidden="true">
            <div class="relative ml-11 h-4 text-[10px] text-muted-foreground">
                <span
                    v-for="hour in ticks"
                    :key="hour"
                    class="absolute -translate-x-1/2 font-time"
                    :style="{ left: `${position(hour)}%` }"
                    >{{ String(hour % 24).padStart(2, '0') }}</span
                >
            </div>

            <div class="relative mt-1 space-y-1.5">
                <div
                    v-for="day in days"
                    :key="day.weekday"
                    class="flex items-center gap-2"
                >
                    <span
                        class="w-9 shrink-0 font-medium"
                        :class="
                            day.isToday
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ day.name }}</span
                    >
                    <div
                        class="relative h-3 flex-1 rounded-full bg-foreground/[0.05]"
                        :class="day.isToday && 'ring-1 ring-foreground/15'"
                    >
                        <span
                            v-for="bar in day.bars"
                            :key="bar.key"
                            class="week-bar absolute inset-y-0 rounded-full bg-[linear-gradient(90deg,var(--from),var(--to))] dark:bg-[linear-gradient(90deg,var(--from-dark),var(--to-dark))]"
                            :class="day.isToday && 'is-today'"
                            :style="bar.style"
                        />
                        <span
                            v-if="day.isToday && needle !== null"
                            class="absolute -inset-y-1.5 w-0.5 -translate-x-1/2 rounded-full bg-sun transition-[left] duration-1000 ease-out"
                            :style="{ left: `${needle}%` }"
                        >
                            <span
                                class="absolute -top-1 left-1/2 size-2 -translate-x-1/2 rounded-full bg-sun motion-safe:animate-pulse"
                            />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.week-bar {
    box-shadow: inset 0 0 0 1px rgb(21 24 31 / 0.08);
    transform-origin: left center;
}

.week-bar.is-today {
    box-shadow: inset 0 0 0 1px rgb(21 24 31 / 0.18);
}

@media (prefers-reduced-motion: no-preference) {
    .week-bar {
        animation: week-bar-grow 600ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes week-bar-grow {
    from {
        transform: scaleX(0);
        opacity: 0;
    }
}
</style>
