<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import TimeAxis from '@/components/calendar/TimeAxis.vue';
import { PPM, useNow, useTimeScale } from '@/composables/useTimeScale';
import { dateIn, minutesOnDay, timeIn } from '@/lib/booking';
import { inLanes, mergeRanges, statusEdge, statusLabel } from '@/lib/calendar';
import { formatDate } from '@/lib/dates';
import { daylight } from '@/lib/daylight';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
} from '@/types/calendar';

/**
 * A week, a column per day. Shaded outside working hours (anyone's, or the
 * one person shown); bookings that overlap sit side by side. Clicking a
 * day's heading opens that day.
 */
const props = defineProps<{
    days: string[];
    today: string;
    timezone: string;
    staff: CalendarStaff[];
    bookings: CalendarBooking[];
    timeOff: CalendarTimeOff[];
}>();

const emit = defineEmits<{
    open: [booking: CalendarBooking];
    day: [date: string];
}>();

const scroller = ref<HTMLElement | null>(null);
const now = useNow();

/** One person shown: their time off shows, and blocks name the customer. */
const onePerson = computed(() => props.staff.length === 1);

const names = computed(
    () => new Map(props.staff.map((member) => [member.id, member.name])),
);

const at = (instant: string, day: string) =>
    minutesOnDay(instant, day, props.timezone);

/** The shortest block drawn, so a quick booking can still be clicked. */
const MIN_MINUTES = 22 / PPM;

const byDay = computed(() => {
    const map = new Map<string, CalendarBooking[]>(
        props.days.map((day) => [day, []]),
    );

    for (const booking of props.bookings) {
        map.get(dateIn(booking.starts_at, props.timezone))?.push(booking);
    }

    return map;
});

function hoursOn(day: string): [number, number][] {
    return mergeRanges(
        props.staff.flatMap((member) => member.hours[day] ?? []),
    );
}

function offOn(day: string) {
    return props.timeOff
        .filter((entry) => onePerson.value || entry.staff_id === null)
        .map((entry) => ({
            entry,
            from: at(entry.starts_at, day),
            to: at(entry.ends_at, day),
        }))
        .filter(({ from, to }) => to > from);
}

const { range, hourMarks, height, top, inRange } = useTimeScale(() => ({
    starts: [
        ...props.days.flatMap((day) => hoursOn(day).map(([from]) => from)),
        ...props.bookings.map((b) =>
            at(b.starts_at, dateIn(b.starts_at, props.timezone)),
        ),
    ],
    ends: [
        ...props.days.flatMap((day) => hoursOn(day).map(([, to]) => to)),
        ...props.bookings.map((b) =>
            at(b.ends_at, dateIn(b.starts_at, props.timezone)),
        ),
    ],
}));

function layout(day: string) {
    return inLanes(byDay.value.get(day) ?? [], (booking) => {
        const start = at(booking.starts_at, day);

        return [start, Math.max(at(booking.ends_at, day), start + MIN_MINUTES)];
    }).map(({ item: booking, lane, lanes }) => {
        const start = at(booking.starts_at, day);
        const minutes = at(booking.ends_at, day) - start;

        return {
            booking,
            lanes,
            top: top(start),
            height: Math.max(22, minutes * PPM),
            short: minutes * PPM < 44,
            left: `calc(0.25rem + (100% - 0.5rem) * ${lane / lanes})`,
            width: `calc((100% - 0.5rem) / ${lanes} - 2px)`,
            tint: daylight(start / 60),
            tintDark: daylight(start / 60, 'dark'),
        };
    });
}

const blocks = computed(
    () => new Map(props.days.map((day) => [day, layout(day)])),
);

/**
 * A day with bookings side by side gets a wider column (up to three times),
 * so each one stays readable.
 */
const columns = computed(() =>
    props.days
        .map((day) => {
            const lanes = Math.min(
                3,
                Math.max(
                    1,
                    ...(blocks.value.get(day) ?? []).map((b) => b.lanes),
                ),
            );

            return `minmax(${5 + 2.5 * lanes}rem, ${lanes}fr)`;
        })
        .join(' '),
);

/** A second line for roomy blocks: the customer for one person, else who. */
function subtitle(booking: CalendarBooking): string {
    if (onePerson.value) {
        return booking.customer?.name ?? 'Walk-in';
    }

    return names.value.get(booking.staff_id ?? 0) ?? '';
}

const nowMinutes = computed(() => {
    const minutes = props.days.includes(props.today)
        ? minutesOnDay(now.value.toISOString(), props.today, props.timezone)
        : null;

    return inRange(minutes) ? minutes : null;
});

onMounted(async () => {
    // Start at now in this week, or at the earliest booking.
    await nextTick();
    const starts = props.bookings.map((b) =>
        at(b.starts_at, dateIn(b.starts_at, props.timezone)),
    );
    const first =
        nowMinutes.value ??
        (starts.length > 0 ? Math.min(...starts) : range.value[0]);
    const box = scroller.value;

    if (!box) {
        return;
    }

    // On a narrow screen, bring today's column into view too.
    const column = box.querySelector<HTMLElement>(
        `[data-test="column-${props.today}"]`,
    );
    const offscreen =
        column && column.offsetLeft + column.offsetWidth > box.clientWidth;

    box.scrollTo({
        top: Math.max(0, top(first) - 120),
        left: offscreen ? column.offsetLeft - 56 : 0,
    });
});
</script>

<template>
    <div
        ref="scroller"
        class="relative max-h-[calc(100svh-15rem)] min-h-96 overflow-auto rounded-xl border bg-card"
        data-test="week-grid"
    >
        <div
            class="grid min-w-full"
            :style="{
                gridTemplateColumns: `3.5rem ${columns}`,
            }"
        >
            <!-- Header row: the days. -->
            <div class="sticky top-0 left-0 z-30 border-b bg-card" />
            <button
                v-for="day in days"
                :key="day"
                type="button"
                class="sticky top-0 z-20 flex items-center gap-2 border-b border-l bg-card px-3 py-2 text-left transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                :aria-label="`Open ${formatDate(day, { weekday: 'long', day: 'numeric', month: 'long' })}`"
                :data-test="`day-${day}`"
                @click="emit('day', day)"
            >
                <span class="text-xs text-muted-foreground uppercase">{{
                    formatDate(day, { weekday: 'short' })
                }}</span>
                <span
                    class="grid size-7 place-items-center rounded-full text-sm font-semibold tabular-nums"
                    :class="
                        day === today && 'bg-primary text-primary-foreground'
                    "
                    >{{ formatDate(day, { day: 'numeric' }) }}</span
                >
                <span
                    v-if="(byDay.get(day) ?? []).length > 0"
                    class="ml-auto text-xs text-muted-foreground"
                >
                    {{ (byDay.get(day) ?? []).length }}
                </span>
            </button>

            <TimeAxis :range="range" :marks="hourMarks" :now="nowMinutes" />

            <!-- A column per day. -->
            <div
                v-for="day in days"
                :key="day"
                class="relative border-l bg-[repeating-linear-gradient(135deg,transparent_0_7px,color-mix(in_oklch,var(--line)_70%,transparent)_7px_8px)]"
                :style="{ height: `${height}px` }"
                :data-test="`column-${day}`"
            >
                <!-- Working hours are the clear parts. -->
                <div
                    v-for="([from, to], index) in hoursOn(day)"
                    :key="index"
                    class="absolute inset-x-0 bg-card"
                    :style="{
                        top: `${top(from)}px`,
                        height: `${(to - from) * PPM}px`,
                    }"
                />

                <div
                    v-for="mark in hourMarks"
                    :key="mark"
                    class="pointer-events-none absolute inset-x-0 border-t border-border/70"
                    :style="{ top: `${top(mark)}px` }"
                />

                <!-- Closures, and the person's own time off when one is shown. -->
                <div
                    v-for="{ entry, from, to } in offOn(day)"
                    :key="`off-${entry.id}`"
                    class="absolute inset-x-1 overflow-hidden rounded-md bg-[repeating-linear-gradient(135deg,var(--muted)_0_6px,color-mix(in_oklch,var(--ink)_10%,transparent)_6px_7px)] px-2 py-1 text-xs text-muted-foreground"
                    :style="{
                        top: `${top(from)}px`,
                        height: `${(to - from) * PPM}px`,
                    }"
                >
                    {{ entry.staff_id === null ? 'Closed' : 'Off' }}
                    <template v-if="entry.reason">
                        · {{ entry.reason }}</template
                    >
                </div>

                <div
                    v-if="nowMinutes !== null && day === today"
                    class="pointer-events-none absolute inset-x-0 z-10 h-px bg-destructive"
                    :style="{ top: `${top(nowMinutes)}px` }"
                    data-test="now-line"
                />

                <button
                    v-for="block in blocks.get(day)"
                    :key="block.booking.id"
                    type="button"
                    class="absolute overflow-hidden rounded-md border-l-[3px] bg-[var(--tint)] px-1.5 py-1 text-left text-xs shadow-sm transition-shadow hover:z-10 hover:shadow-md focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-[var(--tint-dark)]"
                    :class="statusEdge[block.booking.status]"
                    :style="{
                        top: `${block.top}px`,
                        height: `${block.height}px`,
                        left: block.left,
                        width: block.width,
                        '--tint': block.tint,
                        '--tint-dark': block.tintDark,
                    }"
                    :aria-label="`${block.booking.service_name}, ${formatDate(day, { weekday: 'long' })} ${timeIn(block.booking.starts_at, timezone)} to ${timeIn(block.booking.ends_at, timezone)}, ${block.booking.customer?.name ?? 'no customer'}, with ${names.get(block.booking.staff_id ?? 0) ?? 'anyone'}, ${statusLabel[block.booking.status]}`"
                    :data-test="`booking-${block.booking.code}`"
                    @click="emit('open', block.booking)"
                >
                    <span
                        v-if="block.short"
                        class="block truncate leading-tight"
                    >
                        <span class="font-medium tabular-nums">{{
                            timeIn(block.booking.starts_at, timezone)
                        }}</span>
                        ·
                        <span class="font-semibold">{{
                            block.booking.service_name
                        }}</span>
                    </span>
                    <template v-else>
                        <span class="block truncate font-medium tabular-nums">
                            {{ timeIn(block.booking.starts_at, timezone) }}
                        </span>
                        <span class="block truncate font-semibold">{{
                            block.booking.service_name
                        }}</span>
                        <span class="block truncate text-foreground/70">{{
                            subtitle(block.booking)
                        }}</span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
