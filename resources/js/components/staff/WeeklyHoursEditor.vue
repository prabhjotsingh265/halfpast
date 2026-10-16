<script setup lang="ts">
import { Clock, Copy, Plus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { daylightRange } from '@/lib/daylight';
import { duration } from '@/lib/time';
import type { Stretch, Week } from '@/lib/week';
import {
    clockLabel,
    DAY,
    PRESETS,
    sortStretches,
    stretchProblems,
    WEEKDAYS,
    weekMinutes,
} from '@/lib/week';

/**
 * The week as seven tracks. Each stretch can be dragged by its body or its
 * edges, and dragging (or tapping) an empty part of a track adds one. The
 * time dropdowns under each track edit the same stretches, so everything can
 * also be done from the keyboard.
 */
const week = defineModel<Week>({ required: true });

const SNAP = 15;
const MIN_LENGTH = 15;
const OFFICE: Stretch = { start: 9 * 60, end: 17 * 60 };

type DragKind = 'start' | 'end' | 'move' | 'paint';

type Drag = {
    day: number;
    /** -1 while a paint hasn't moved far enough to create a stretch yet. */
    index: number;
    kind: DragKind;
    pointerId: number;
    originX: number;
    origin: number;
    start: number;
    end: number;
    low: number;
    high: number;
};

const drag = ref<Drag | null>(null);
// The visible span stays put while dragging, so the track doesn't rescale under the pointer.
const frozenSpan = ref<[number, number] | null>(null);
const tracks = ref<(HTMLElement | null)[]>([]);
// Hours a day had before it was switched off, to bring back if it's switched on again.
const remembered = new Map<number, Stretch[]>();

const span = computed<[number, number]>(() => {
    if (frozenSpan.value) {
        return frozenSpan.value;
    }

    const all = week.value.flat();
    const earliest = Math.min(6 * 60, ...all.map((s) => s.start));
    const latest = Math.max(22 * 60, ...all.map((s) => s.end));

    return [
        Math.max(0, Math.floor(earliest / 60) * 60),
        Math.min(DAY, Math.ceil(latest / 60) * 60),
    ];
});

const hourFormat = new Intl.DateTimeFormat(undefined, { hour: 'numeric' });

const ticks = computed(() => {
    const [from, to] = span.value;
    const hours = (to - from) / 60;
    const every = hours > 18 ? 4 : hours > 12 ? 3 : 2;
    const list: { minutes: number; label: string | null }[] = [];

    for (let minutes = from; minutes <= to; minutes += 60) {
        const labelled = ((minutes - from) / 60) % every === 0 && minutes < DAY;

        list.push({
            minutes,
            label: labelled
                ? hourFormat.format(new Date(2000, 0, 1, minutes / 60))
                : null,
        });
    }

    return list;
});

const totalLabel = computed(() => {
    const minutes = weekMinutes(week.value);

    return minutes === 0 ? 'No hours yet' : `${duration(minutes)} a week`;
});

const problems = computed(() => week.value.map(stretchProblems));

const startOptions = Array.from({ length: DAY / SNAP }, (_, i) => i * SNAP);
const endOptions = Array.from({ length: DAY / SNAP }, (_, i) => (i + 1) * SNAP);

/** Keep a value that's off the 15-minute grid (saved some other way) selectable. */
function optionsFor(options: number[], value: number): number[] {
    return options.includes(value)
        ? options
        : [...options, value].sort((a, b) => a - b);
}

function percent(minutes: number): number {
    const [from, to] = span.value;

    return ((minutes - from) / (to - from)) * 100;
}

/** Keep the first and last ruler labels inside the ruler. */
function labelShift(minutes: number): string {
    const at = percent(minutes);

    if (at < 4) {
        return 'translate-x-0';
    }

    return at > 96 ? '-translate-x-full' : '-translate-x-1/2';
}

function blockStyle(stretch: Stretch): Record<string, string> {
    const start = Math.max(stretch.start, span.value[0]);
    const end = Math.max(start, Math.min(stretch.end, span.value[1]));

    return {
        left: `${percent(start)}%`,
        width: `${percent(end) - percent(start)}%`,
        ...daylightRange(start / 60, end / 60),
    };
}

const clamp = (value: number, low: number, high: number) =>
    Math.min(high, Math.max(low, value));

const snap = (minutes: number) => Math.round(minutes / SNAP) * SNAP;

function setDay(day: number, stretches: Stretch[]): void {
    week.value = week.value.map((existing, i) =>
        i === day ? stretches : existing,
    );
}

function setStretch(day: number, index: number, stretch: Stretch): void {
    setDay(
        day,
        week.value[day].map((existing, i) =>
            i === index ? stretch : existing,
        ),
    );
}

function minutesAt(day: number, clientX: number): number {
    const track = tracks.value[day];

    if (!track) {
        return span.value[0];
    }

    const rect = track.getBoundingClientRect();
    const [from, to] = span.value;
    const ratio = clamp((clientX - rect.left) / rect.width, 0, 1);

    return from + ratio * (to - from);
}

/** The free room around a stretch (or a point), between its neighbours. */
function room(
    day: number,
    except: number,
    from: number,
    to: number,
): [number, number] {
    let low = span.value[0];
    let high = span.value[1];

    week.value[day].forEach((stretch, i) => {
        if (i === except) {
            return;
        }

        if (stretch.end <= from) {
            low = Math.max(low, stretch.end);
        }

        if (stretch.start >= to) {
            high = Math.min(high, stretch.start);
        }
    });

    return [low, high];
}

function grab(
    event: PointerEvent,
    day: number,
    index: number,
    kind: DragKind,
): void {
    if (event.button !== 0) {
        return;
    }

    event.stopPropagation();
    event.preventDefault();

    const stretch = week.value[day][index];
    const [low, high] = room(day, index, stretch.start, stretch.end);

    frozenSpan.value = span.value;
    drag.value = {
        day,
        index,
        kind,
        pointerId: event.pointerId,
        originX: event.clientX,
        origin: minutesAt(day, event.clientX),
        start: stretch.start,
        end: stretch.end,
        low,
        high,
    };
    listen();
}

function paint(event: PointerEvent, day: number): void {
    if (event.button !== 0) {
        return;
    }

    const at = minutesAt(day, event.clientX);
    const [low, high] = room(day, -1, at, at);

    if (high - low < MIN_LENGTH) {
        return;
    }

    const anchor = clamp(Math.floor(at / SNAP) * SNAP, low, high - MIN_LENGTH);

    frozenSpan.value = span.value;
    drag.value = {
        day,
        index: -1,
        kind: 'paint',
        pointerId: event.pointerId,
        originX: event.clientX,
        origin: anchor,
        start: anchor,
        end: anchor + MIN_LENGTH,
        low,
        high,
    };
    listen();
}

function onMove(event: PointerEvent): void {
    const current = drag.value;

    if (!current || event.pointerId !== current.pointerId) {
        return;
    }

    const at = minutesAt(current.day, event.clientX);

    if (current.kind === 'paint') {
        // A paint only starts once the pointer has moved sideways, so a
        // finger scrolling the page past a track doesn't add hours.
        if (
            current.index === -1 &&
            Math.abs(event.clientX - current.originX) < 6
        ) {
            return;
        }

        const cell = clamp(
            Math.floor(at / SNAP) * SNAP,
            current.low,
            current.high - MIN_LENGTH,
        );
        const stretch = {
            start: Math.min(current.origin, cell),
            end: Math.min(
                current.high,
                Math.max(current.origin, cell) + MIN_LENGTH,
            ),
        };

        // The model only catches up after the parent re-renders, so each
        // event writes the week once instead of reading back its own change.
        if (current.index === -1) {
            const stretches = [...week.value[current.day], stretch];

            current.index = stretches.length - 1;
            setDay(current.day, stretches);
        } else {
            setStretch(current.day, current.index, stretch);
        }

        return;
    }

    let { start, end } = current;

    if (current.kind === 'start') {
        start = clamp(snap(at), current.low, current.end - MIN_LENGTH);
    } else if (current.kind === 'end') {
        end = clamp(snap(at), current.start + MIN_LENGTH, current.high);
    } else {
        const length = current.end - current.start;

        start = clamp(
            current.start + snap(at - current.origin),
            current.low,
            current.high - length,
        );
        end = start + length;
    }

    setStretch(current.day, current.index, { start, end });
}

function onUp(event: PointerEvent): void {
    const current = drag.value;

    if (!current || event.pointerId !== current.pointerId) {
        return;
    }

    let stretches = week.value[current.day];

    if (current.index === -1 && event.type === 'pointerup') {
        // A tap on an empty part of the track adds an hour from there.
        const end = Math.min(current.high, current.origin + 60);
        const start = Math.max(current.low, Math.min(current.origin, end - 60));

        stretches = [...stretches, { start, end }];
    }

    setDay(current.day, sortStretches(stretches));
    stop();
}

function listen(): void {
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
}

function stop(): void {
    drag.value = null;
    frozenSpan.value = null;
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onUp);
}

onBeforeUnmount(stop);

function isDragging(day: number, index: number): boolean {
    return drag.value?.day === day && drag.value.index === index;
}

function toggleDay(day: number): void {
    const stretches = week.value[day];

    if (stretches.length > 0) {
        remembered.set(day, stretches);
        setDay(day, []);

        return;
    }

    // Switching a day on brings back its old hours, or borrows the nearest
    // earlier working day's, so turning on Saturday after Friday is one click.
    const earlier = week.value
        .slice(0, day)
        .reverse()
        .find((s) => s.length > 0);

    setDay(
        day,
        (remembered.get(day) ?? earlier ?? [OFFICE]).map((s) => ({ ...s })),
    );
}

function addStretch(day: number): void {
    const stretches = week.value[day];

    if (stretches.length === 0) {
        toggleDay(day);

        return;
    }

    const latest = Math.max(...stretches.map((s) => s.end));
    const start = latest + 30;

    setDay(day, [...stretches, { start, end: Math.min(DAY, start + 120) }]);
}

function canAdd(day: number): boolean {
    const stretches = week.value[day];

    return (
        stretches.length === 0 ||
        Math.max(...stretches.map((s) => s.end)) + 30 + MIN_LENGTH <= DAY
    );
}

function removeStretch(day: number, index: number): void {
    setDay(
        day,
        week.value[day].filter((_, i) => i !== index),
    );
}

function copyTo(day: number, targets: number[]): void {
    const source = week.value[day];

    week.value = week.value.map((stretches, i) =>
        targets.includes(i) && i !== day
            ? source.map((s) => ({ ...s }))
            : stretches,
    );
}

function applyPreset(preset: (typeof PRESETS)[number]): void {
    remembered.clear();
    week.value = preset.week();
}

function onSelect(
    day: number,
    index: number,
    edge: 'start' | 'end',
    event: Event,
): void {
    const value = Number((event.target as HTMLSelectElement).value);
    const stretch = week.value[day][index];

    setStretch(day, index, { ...stretch, [edge]: value });
}
</script>

<template>
    <div
        class="rounded-xl border bg-card"
        :class="{ 'cursor-grabbing select-none': drag }"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-muted-foreground">Start from</span>
                <button
                    v-for="preset in PRESETS"
                    :key="preset.label"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition-colors hover:border-foreground/30 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>
            <p
                class="flex items-center gap-1.5 text-sm font-medium font-time"
                aria-live="polite"
                data-test="week-total"
            >
                <Clock class="size-3.5 text-muted-foreground" />
                {{ totalLabel }}
            </p>
        </div>

        <div class="px-4">
            <!-- Hour ruler, lined up with the tracks below. -->
            <div
                class="grid pt-3 md:grid-cols-[9.5rem_minmax(0,1fr)] md:gap-4"
                aria-hidden="true"
            >
                <span class="hidden md:block" />
                <div class="relative h-4">
                    <span
                        v-for="tick in ticks.filter((t) => t.label)"
                        :key="tick.minutes"
                        class="absolute text-[10px] whitespace-nowrap text-muted-foreground font-time"
                        :class="labelShift(tick.minutes)"
                        :style="{ left: `${percent(tick.minutes)}%` }"
                    >
                        {{ tick.label }}
                    </span>
                </div>
            </div>

            <div class="divide-y">
                <div
                    v-for="(day, dayIndex) in WEEKDAYS"
                    :key="day.iso"
                    class="grid gap-3 py-4 md:grid-cols-[9.5rem_minmax(0,1fr)] md:gap-4"
                    :data-test="`day-${day.iso}`"
                >
                    <div class="flex items-center gap-3 md:h-10">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="week[dayIndex].length > 0"
                            :aria-label="`Works on ${day.long}`"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                            :class="
                                week[dayIndex].length > 0
                                    ? 'bg-sun-deep'
                                    : 'bg-muted-foreground/30'
                            "
                            @click="toggleDay(dayIndex)"
                        >
                            <span
                                class="size-4 rounded-full bg-background shadow-sm transition-transform duration-200"
                                :class="
                                    week[dayIndex].length > 0
                                        ? 'translate-x-4.5'
                                        : 'translate-x-0.5'
                                "
                            />
                        </button>
                        <span class="flex-1 font-medium">
                            {{ day.long }}
                        </span>
                    </div>

                    <div class="min-w-0 space-y-3">
                        <div
                            :ref="
                                (el) => (tracks[dayIndex] = el as HTMLElement)
                            "
                            class="relative h-10 cursor-copy touch-pan-y overflow-hidden rounded-lg bg-muted/70 ring-1 ring-border ring-inset"
                            :class="
                                week[dayIndex].length === 0 &&
                                'bg-[repeating-linear-gradient(135deg,transparent_0_7px,var(--line)_7px_8px)]'
                            "
                            aria-hidden="true"
                            @pointerdown="paint($event, dayIndex)"
                        >
                            <span
                                v-for="tick in ticks"
                                :key="tick.minutes"
                                class="pointer-events-none absolute inset-y-0 w-px bg-border"
                                :style="{ left: `${percent(tick.minutes)}%` }"
                            />

                            <p
                                v-if="week[dayIndex].length === 0"
                                class="pointer-events-none absolute inset-0 grid place-items-center text-xs text-muted-foreground"
                            >
                                Day off · drag or tap to add hours
                            </p>

                            <div
                                v-for="(stretch, index) in week[dayIndex]"
                                :key="index"
                                class="group absolute inset-y-1 animate-in cursor-grab touch-pan-y overflow-hidden rounded-md bg-[linear-gradient(90deg,var(--from),var(--to))] shadow-sm zoom-in-95 fade-in ring-inset dark:bg-[linear-gradient(90deg,var(--from-dark),var(--to-dark))]"
                                :class="[
                                    !isDragging(dayIndex, index) &&
                                        'transition-[left,width] duration-200 ease-out motion-reduce:transition-none',
                                    isDragging(dayIndex, index)
                                        ? 'z-10 shadow-md ring-2 ring-sun-deep'
                                        : problems[dayIndex].has(index)
                                          ? 'ring-2 ring-destructive'
                                          : 'ring-1 ring-black/10 dark:ring-white/15',
                                ]"
                                :style="blockStyle(stretch)"
                                @pointerdown="
                                    grab($event, dayIndex, index, 'move')
                                "
                            >
                                <span
                                    class="pointer-events-none absolute inset-0 truncate px-3 text-center text-[11px] leading-8 font-medium font-time"
                                >
                                    {{ clockLabel(stretch.start) }} –
                                    {{ clockLabel(stretch.end) }}
                                </span>
                                <span
                                    class="absolute inset-y-0 left-0 flex w-3 cursor-ew-resize touch-none items-center justify-center"
                                    @pointerdown="
                                        grab($event, dayIndex, index, 'start')
                                    "
                                >
                                    <span
                                        class="h-4 w-1 rounded-full bg-foreground/40 opacity-0 transition-opacity group-hover:opacity-100"
                                    />
                                </span>
                                <span
                                    class="absolute inset-y-0 right-0 flex w-3 cursor-ew-resize touch-none items-center justify-center"
                                    @pointerdown="
                                        grab($event, dayIndex, index, 'end')
                                    "
                                >
                                    <span
                                        class="h-4 w-1 rounded-full bg-foreground/40 opacity-0 transition-opacity group-hover:opacity-100"
                                    />
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="week[dayIndex].length > 0"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <div
                                v-for="(stretch, index) in week[dayIndex]"
                                :key="index"
                                class="flex items-center gap-1 rounded-lg border bg-background py-1 pr-1 pl-2"
                                :class="
                                    problems[dayIndex].has(index) &&
                                    'border-destructive'
                                "
                            >
                                <select
                                    :value="stretch.start"
                                    :aria-label="`${day.long} starts`"
                                    class="h-7 rounded bg-transparent text-sm font-time outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    @change="
                                        onSelect(
                                            dayIndex,
                                            index,
                                            'start',
                                            $event,
                                        )
                                    "
                                >
                                    <option
                                        v-for="minutes in optionsFor(
                                            startOptions,
                                            stretch.start,
                                        )"
                                        :key="minutes"
                                        :value="minutes"
                                    >
                                        {{ clockLabel(minutes) }}
                                    </option>
                                </select>
                                <span class="text-muted-foreground">–</span>
                                <select
                                    :value="stretch.end"
                                    :aria-label="`${day.long} ends`"
                                    class="h-7 rounded bg-transparent text-sm font-time outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    @change="
                                        onSelect(dayIndex, index, 'end', $event)
                                    "
                                >
                                    <option
                                        v-for="minutes in optionsFor(
                                            endOptions,
                                            stretch.end,
                                        )"
                                        :key="minutes"
                                        :value="minutes"
                                    >
                                        {{ clockLabel(minutes) }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="grid size-6 place-items-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :aria-label="`Remove ${day.long} ${clockLabel(stretch.start)} to ${clockLabel(stretch.end)}`"
                                    @click="removeStretch(dayIndex, index)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>

                            <Button
                                v-if="canAdd(dayIndex)"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 text-muted-foreground"
                                @click="addStretch(dayIndex)"
                            >
                                <Plus class="size-3.5" />
                                Add hours
                            </Button>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 text-muted-foreground"
                                        :aria-label="`Copy ${day.long}'s hours`"
                                    >
                                        <Copy class="size-3.5" />
                                        <span class="hidden sm:inline"
                                            >Copy to…</span
                                        >
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuLabel>
                                        Copy {{ day.long }}'s hours to
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        @select="
                                            copyTo(dayIndex, [0, 1, 2, 3, 4])
                                        "
                                        >Weekdays</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        @select="copyTo(dayIndex, [5, 6])"
                                        >The weekend</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        @select="
                                            copyTo(
                                                dayIndex,
                                                [0, 1, 2, 3, 4, 5, 6],
                                            )
                                        "
                                        >Every day</DropdownMenuItem
                                    >
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <p
                            v-for="[index, problem] in problems[dayIndex]"
                            :key="index"
                            class="text-sm text-destructive"
                        >
                            {{ clockLabel(week[dayIndex][index].start) }} –
                            {{ clockLabel(week[dayIndex][index].end) }}:
                            {{ problem.toLowerCase() }}.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
