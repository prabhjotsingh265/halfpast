<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import { minutesOnDay, timeIn } from '@/lib/booking';
import { daylight } from '@/lib/daylight';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
} from '@/types/calendar';

/**
 * One day, a column per person. Working hours are clear and the rest is
 * shaded; bookings sit on top, coloured by the hour they start, with their
 * tidy-up time after them. A line marks now.
 */
const props = defineProps<{
    date: string;
    today: string;
    timezone: string;
    staff: CalendarStaff[];
    bookings: CalendarBooking[];
    timeOff: CalendarTimeOff[];
}>();

const emit = defineEmits<{ open: [booking: CalendarBooking] }>();

/** Pixels per minute: an hour is 90px tall. */
const PPM = 1.5;

const scroller = ref<HTMLElement | null>(null);
const now = ref(new Date());
let timer = 0;

const at = (instant: string) =>
    minutesOnDay(instant, props.date, props.timezone);

/** From the earliest start to the latest finish that day, in whole hours. */
const range = computed<[number, number]>(() => {
    const starts = [
        ...props.staff.flatMap((s) => s.hours.map(([from]) => from)),
        ...props.bookings.map((b) => at(b.starts_at)),
    ];
    const ends = [
        ...props.staff.flatMap((s) => s.hours.map(([, to]) => to)),
        ...props.bookings.map((b) => at(b.blocked_until)),
    ];

    if (starts.length === 0) {
        return [9 * 60, 18 * 60];
    }

    const from = Math.max(0, Math.floor(Math.min(...starts) / 60) * 60 - 60);
    const to = Math.min(24 * 60, Math.ceil(Math.max(...ends) / 60) * 60 + 60);

    return [from, Math.max(to, from + 8 * 60)];
});

const hourMarks = computed(() => {
    const marks: number[] = [];

    for (let m = range.value[0]; m <= range.value[1]; m += 60) {
        marks.push(m);
    }

    return marks;
});

const height = computed(() => (range.value[1] - range.value[0]) * PPM);
const top = (minutes: number) => (minutes - range.value[0]) * PPM;

const hourLabel = new Intl.DateTimeFormat(undefined, { hour: 'numeric' });
function labelFor(minutes: number): string {
    return hourLabel.format(new Date(2000, 0, 1, Math.floor(minutes / 60)));
}

function bookingsFor(staffId: number): CalendarBooking[] {
    return props.bookings.filter((b) => b.staff_id === staffId);
}

function offFor(staffId: number): CalendarTimeOff[] {
    return props.timeOff.filter(
        (t) => t.staff_id === null || t.staff_id === staffId,
    );
}

function block(booking: CalendarBooking) {
    const start = at(booking.starts_at);
    const end = at(booking.ends_at);
    const blocked = at(booking.blocked_until);
    const hour = start / 60;

    return {
        top: top(start),
        height: Math.max(22, (end - start) * PPM),
        buffer: Math.max(0, (blocked - end) * PPM),
        tint: daylight(hour),
        tintDark: daylight(hour, 'dark'),
        short: (end - start) * PPM < 44,
    };
}

const statusLabel: Record<CalendarBooking['status'], string> = {
    held: 'Held',
    confirmed: 'Confirmed',
    completed: 'Completed',
    no_show: 'No-show',
};

const nowMinutes = computed(() =>
    props.date === props.today
        ? minutesOnDay(now.value.toISOString(), props.date, props.timezone)
        : null,
);
const nowVisible = computed(
    () =>
        nowMinutes.value !== null &&
        nowMinutes.value >= range.value[0] &&
        nowMinutes.value <= range.value[1],
);

onMounted(async () => {
    timer = window.setInterval(() => (now.value = new Date()), 30_000);

    // Start the view at now (today) or at the first booking.
    await nextTick();
    const first =
        nowMinutes.value ??
        (props.bookings[0] ? at(props.bookings[0].starts_at) : range.value[0]);
    scroller.value?.scrollTo({ top: Math.max(0, top(first) - 120) });
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <div
        ref="scroller"
        class="relative max-h-[calc(100svh-15rem)] min-h-96 overflow-auto rounded-xl border bg-card"
        data-test="day-grid"
    >
        <div
            class="grid min-w-full"
            :style="{
                gridTemplateColumns: `3.5rem repeat(${Math.max(1, staff.length)}, minmax(11rem, 1fr))`,
            }"
        >
            <!-- Header row: who each column is. -->
            <div class="sticky top-0 left-0 z-30 border-b bg-card" />
            <div
                v-for="member in staff"
                :key="member.id"
                class="sticky top-0 z-20 flex items-center gap-2 border-b border-l bg-card px-3 py-2"
            >
                <MemberAvatar :name="member.name" size="xs" />
                <span class="truncate text-sm font-medium">{{
                    member.name
                }}</span>
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ bookingsFor(member.id).length }}
                </span>
            </div>

            <!-- Time axis. -->
            <div
                class="sticky left-0 z-10 bg-card"
                :style="{ height: `${height}px` }"
            >
                <span
                    v-for="mark in hourMarks"
                    :key="mark"
                    class="absolute right-2 -translate-y-1/2 text-[10px] text-muted-foreground tabular-nums"
                    :style="{ top: `${top(mark)}px` }"
                >
                    {{ mark < 24 * 60 ? labelFor(mark) : '' }}
                </span>
                <span
                    v-if="nowVisible"
                    class="absolute right-0 size-2 translate-x-1/2 -translate-y-1/2 rounded-full bg-destructive"
                    :style="{ top: `${top(nowMinutes!)}px` }"
                    data-test="now-dot"
                />
            </div>

            <!-- A column per person. -->
            <div
                v-for="member in staff"
                :key="member.id"
                class="relative border-l bg-[repeating-linear-gradient(135deg,transparent_0_7px,color-mix(in_oklch,var(--line)_70%,transparent)_7px_8px)]"
                :style="{ height: `${height}px` }"
                :data-test="`column-${member.id}`"
            >
                <!-- Working hours are the clear parts. -->
                <div
                    v-for="([from, to], index) in member.hours"
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

                <!-- Time off and closures. -->
                <div
                    v-for="off in offFor(member.id)"
                    :key="`off-${off.id}`"
                    class="absolute inset-x-1 overflow-hidden rounded-md bg-[repeating-linear-gradient(135deg,var(--muted)_0_6px,color-mix(in_oklch,var(--ink)_10%,transparent)_6px_7px)] px-2 py-1 text-xs text-muted-foreground"
                    :style="{
                        top: `${top(at(off.starts_at))}px`,
                        height: `${(at(off.ends_at) - at(off.starts_at)) * PPM}px`,
                    }"
                >
                    {{ off.staff_id === null ? 'Closed' : 'Off' }}
                    <template v-if="off.reason"> · {{ off.reason }}</template>
                </div>

                <div
                    v-if="nowVisible"
                    class="pointer-events-none absolute inset-x-0 z-10 h-px bg-destructive"
                    :style="{ top: `${top(nowMinutes!)}px` }"
                    data-test="now-line"
                />

                <!-- Bookings. -->
                <template
                    v-for="booking in bookingsFor(member.id)"
                    :key="booking.id"
                >
                    <div
                        v-if="block(booking).buffer > 0"
                        class="pointer-events-none absolute inset-x-1 rounded-b-md bg-[repeating-linear-gradient(135deg,transparent_0_4px,color-mix(in_oklch,var(--ink)_8%,transparent)_4px_5px)]"
                        :style="{
                            top: `${block(booking).top + block(booking).height}px`,
                            height: `${block(booking).buffer}px`,
                        }"
                        aria-hidden="true"
                    />
                    <button
                        type="button"
                        class="booking absolute inset-x-1 overflow-hidden rounded-md border-l-[3px] bg-[var(--tint)] px-2 py-1 text-left text-xs shadow-sm transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-[var(--tint-dark)]"
                        :class="{
                            'border-sun-deep': booking.status === 'confirmed',
                            'border-2 border-dashed border-sun-deep/70':
                                booking.status === 'held',
                            'border-success': booking.status === 'completed',
                            'border-destructive opacity-70':
                                booking.status === 'no_show',
                        }"
                        :style="{
                            top: `${block(booking).top}px`,
                            height: `${block(booking).height}px`,
                            '--tint': block(booking).tint,
                            '--tint-dark': block(booking).tintDark,
                        }"
                        :aria-label="`${booking.service_name}, ${timeIn(booking.starts_at, timezone)} to ${timeIn(booking.ends_at, timezone)}, ${booking.customer?.name ?? 'no customer'}, ${statusLabel[booking.status]}`"
                        :data-test="`booking-${booking.code}`"
                        @click="emit('open', booking)"
                    >
                        <!-- A short appointment gets one line: time and service. -->
                        <span
                            v-if="block(booking).short"
                            class="block truncate leading-tight"
                        >
                            <span class="font-medium tabular-nums">{{
                                timeIn(booking.starts_at, timezone)
                            }}</span>
                            ·
                            <span class="font-semibold">{{
                                booking.service_name
                            }}</span>
                        </span>
                        <template v-else>
                            <span
                                class="flex items-center gap-1 font-medium tabular-nums"
                            >
                                {{ timeIn(booking.starts_at, timezone) }}
                                <span
                                    v-if="booking.status !== 'confirmed'"
                                    class="rounded bg-background/70 px-1 text-[10px] font-semibold"
                                    >{{ statusLabel[booking.status] }}</span
                                >
                            </span>
                            <span class="block truncate font-semibold">{{
                                booking.service_name
                            }}</span>
                            <span class="block truncate text-foreground/70">{{
                                booking.customer?.name ?? 'Walk-in'
                            }}</span>
                        </template>
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
