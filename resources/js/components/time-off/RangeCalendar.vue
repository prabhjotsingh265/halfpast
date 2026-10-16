<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    addDays,
    addMonths,
    formatDate,
    monthLabel,
    monthWeeks,
    startOfMonth,
} from '@/lib/dates';
import { WEEKDAYS } from '@/lib/week';
import type { CalendarMark } from '@/types/time-off';

/**
 * Two months side by side (one on a phone) for picking a run of days. Click
 * the first day and then the last, or drag across them. Days already off are
 * marked, closures with a hatch and people with a dot. Arrow keys move
 * between days and Enter picks one.
 */
const props = defineProps<{
    today: string;
    marks: Map<string, CalendarMark[]>;
}>();

const from = defineModel<string | null>('from', { required: true });
const to = defineModel<string | null>('to', { required: true });

const view = ref(startOfMonth(from.value ?? props.today));
const anchor = ref<string | null>(null);
const hovered = ref<string | null>(null);
const dragging = ref(false);
const dragged = ref(false);
const focused = ref(from.value ?? props.today);
const grid = ref<HTMLElement | null>(null);

const months = computed(() =>
    [view.value, addMonths(view.value, 1)].map((month) => ({
        month,
        label: monthLabel(month),
        weeks: monthWeeks(month),
    })),
);

const canGoBack = computed(() => view.value > startOfMonth(props.today));

// A range picked somewhere else (the date fields) brings its month into view.
watch(from, (value) => {
    if (value && (value < view.value || value >= addMonths(view.value, 2))) {
        view.value = startOfMonth(value);
    }
});

/** The range on show: the picked one, or the one being chosen right now. */
const shown = computed<[string, string] | null>(() => {
    if (anchor.value && hovered.value && !dragging.value) {
        return anchor.value < hovered.value
            ? [anchor.value, hovered.value]
            : [hovered.value, anchor.value];
    }

    return from.value && to.value ? [from.value, to.value] : null;
});

function pick(start: string, end: string): void {
    [from.value, to.value] = start <= end ? [start, end] : [end, start];
}

function onPointerDown(event: PointerEvent, date: string): void {
    if (event.button !== 0) {
        return;
    }

    focused.value = date;

    // Second click of a click-then-click choice.
    if (anchor.value && anchor.value !== date) {
        pick(anchor.value, date);
        anchor.value = null;

        return;
    }

    anchor.value = date;
    dragging.value = true;
    dragged.value = false;
    pick(date, date);
    window.addEventListener('pointerup', onPointerUp, { once: true });
}

function onPointerEnter(date: string): void {
    hovered.value = date;

    if (dragging.value && anchor.value) {
        dragged.value = dragged.value || date !== anchor.value;
        pick(anchor.value, date);
    }
}

function onPointerUp(): void {
    dragging.value = false;

    // A drag picks the whole range at once; a single click waits for the last day.
    if (dragged.value) {
        anchor.value = null;
    }
}

/** Enter or Space on a focused day (a keyboard click has no pointer). */
function onKeyPick(event: MouseEvent, date: string): void {
    if (event.detail !== 0) {
        return;
    }

    if (anchor.value) {
        pick(anchor.value, date);
        anchor.value = null;
    } else {
        anchor.value = date;
        pick(date, date);
    }
}

const MOVES: Record<string, number> = {
    ArrowLeft: -1,
    ArrowRight: 1,
    ArrowUp: -7,
    ArrowDown: 7,
};

async function onKeyDown(event: KeyboardEvent): Promise<void> {
    if (event.key === 'Escape') {
        anchor.value = null;

        return;
    }

    const step = MOVES[event.key];

    if (step === undefined) {
        return;
    }

    event.preventDefault();

    const next = addDays(focused.value, step);

    if (next < props.today) {
        return;
    }

    if (next < view.value) {
        view.value = addMonths(view.value, -1);
    } else if (next >= addMonths(view.value, 2)) {
        view.value = addMonths(view.value, 1);
    }

    focused.value = next;
    hovered.value = next;

    await nextTick();
    grid.value
        ?.querySelector<HTMLButtonElement>(`[data-date="${next}"]`)
        ?.focus();
}

function dayState(date: string) {
    const range = shown.value;
    const inRange = range !== null && date >= range[0] && date <= range[1];
    const marks = props.marks.get(date) ?? [];

    return {
        past: date < props.today,
        today: date === props.today,
        inRange,
        start: inRange && date === range[0],
        end: inRange && date === range[1],
        closed: marks.some((m) => m.tone === 'closed'),
        people: marks.filter((m) => m.tone === 'staff').length,
        title: marks.map((m) => m.label).join('\n'),
    };
}
</script>

<template>
    <div ref="grid" class="select-none" @keydown="onKeyDown">
        <div class="mb-3 flex items-center justify-between">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                :disabled="!canGoBack"
                aria-label="Previous month"
                @click="view = addMonths(view, -1)"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <p class="text-xs text-muted-foreground" aria-live="polite">
                <template v-if="anchor && !dragging"
                    >Now click the last day, or keep just this one</template
                >
                <template v-else>Click the first day, then the last</template>
            </p>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Next month"
                @click="view = addMonths(view, 1)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div
                v-for="(month, monthIndex) in months"
                :key="month.month"
                :class="monthIndex === 1 && 'hidden md:block'"
            >
                <h3 class="mb-2 text-center text-sm font-semibold">
                    {{ month.label }}
                </h3>
                <div
                    role="group"
                    :aria-label="month.label"
                    class="grid grid-cols-7 gap-y-1 text-center"
                    @pointerleave="hovered = null"
                >
                    <span
                        v-for="day in WEEKDAYS"
                        :key="day.iso"
                        class="pb-1 text-[11px] font-medium text-muted-foreground"
                        aria-hidden="true"
                        >{{ day.short.slice(0, 2) }}</span
                    >
                    <template v-for="(week, w) in month.weeks" :key="w">
                        <template v-for="(date, d) in week" :key="`${w}-${d}`">
                            <span v-if="date === null" />
                            <div
                                v-else
                                class="relative h-10"
                                :class="[
                                    dayState(date).inRange &&
                                        'bg-sun/20 dark:bg-sun/15',
                                    dayState(date).start && 'rounded-l-full',
                                    dayState(date).end && 'rounded-r-full',
                                ]"
                            >
                                <button
                                    type="button"
                                    :data-date="date"
                                    :tabindex="date === focused ? 0 : -1"
                                    :disabled="dayState(date).past"
                                    :title="dayState(date).title || undefined"
                                    :aria-label="`${formatDate(date, { weekday: 'long', day: 'numeric', month: 'long' })}${dayState(date).title ? `, ${dayState(date).title.replaceAll('\n', ', ')}` : ''}`"
                                    :aria-pressed="dayState(date).inRange"
                                    class="relative mx-auto grid size-10 touch-manipulation place-items-center rounded-full text-sm font-time transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:text-muted-foreground/40"
                                    :class="[
                                        dayState(date).start ||
                                        dayState(date).end
                                            ? 'bg-sun-deep font-semibold text-background'
                                            : !dayState(date).past &&
                                              'hover:bg-accent',
                                        dayState(date).today &&
                                            !dayState(date).start &&
                                            !dayState(date).end &&
                                            'font-semibold ring-1 ring-sun-deep/60 ring-inset',
                                        dayState(date).closed &&
                                            !dayState(date).start &&
                                            !dayState(date).end &&
                                            'bg-[repeating-linear-gradient(135deg,transparent_0_4px,color-mix(in_oklch,var(--dusk)_30%,transparent)_4px_5px)] dark:bg-[repeating-linear-gradient(135deg,transparent_0_4px,color-mix(in_oklch,var(--ink)_25%,transparent)_4px_5px)]',
                                    ]"
                                    @pointerdown="onPointerDown($event, date)"
                                    @pointerenter="onPointerEnter(date)"
                                    @focus="focused = date"
                                    @click="onKeyPick($event, date)"
                                >
                                    {{ Number(date.slice(8)) }}
                                    <span
                                        v-if="dayState(date).people"
                                        class="pointer-events-none absolute bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5"
                                        aria-hidden="true"
                                    >
                                        <span
                                            v-for="n in Math.min(
                                                3,
                                                dayState(date).people,
                                            )"
                                            :key="n"
                                            class="size-1 rounded-full"
                                            :class="
                                                dayState(date).start ||
                                                dayState(date).end
                                                    ? 'bg-background'
                                                    : 'bg-sun-deep'
                                            "
                                        />
                                    </span>
                                </button>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>

        <div
            class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="size-3 rounded-full bg-[repeating-linear-gradient(135deg,transparent_0_2px,color-mix(in_oklch,var(--dusk)_45%,transparent)_2px_3px)] ring-1 ring-border dark:bg-[repeating-linear-gradient(135deg,transparent_0_2px,color-mix(in_oklch,var(--ink)_35%,transparent)_2px_3px)]"
                />
                Business closed
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-1.5 rounded-full bg-sun-deep" />
                Someone's off
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-3 rounded-full ring-1 ring-sun-deep/60" />
                Today
            </span>
        </div>
    </div>
</template>
