<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import TimeAxis from '@/components/calendar/TimeAxis.vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import { PPM, useNow, useTimeScale } from '@/composables/useTimeScale';
import { minutesOnDay, timeIn } from '@/lib/booking';
import { statusEdge, statusLabel } from '@/lib/calendar';
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

const scroller = ref<HTMLElement | null>(null);
const now = useNow();

const at = (instant: string) =>
    minutesOnDay(instant, props.date, props.timezone);

const hoursOf = (member: CalendarStaff) => member.hours[props.date] ?? [];

const { range, hourMarks, height, top, inRange } = useTimeScale(() => ({
    starts: [
        ...props.staff.flatMap((s) => hoursOf(s).map(([from]) => from)),
        ...props.bookings.map((b) => at(b.starts_at)),
    ],
    ends: [
        ...props.staff.flatMap((s) => hoursOf(s).map(([, to]) => to)),
        ...props.bookings.map((b) => at(b.blocked_until)),
    ],
}));

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

const nowMinutes = computed(() => {
    const minutes =
        props.date === props.today
            ? minutesOnDay(now.value.toISOString(), props.date, props.timezone)
            : null;

    return inRange(minutes) ? minutes : null;
});

onMounted(async () => {
    // Start the view at now (today) or at the first booking.
    await nextTick();
    const first =
        nowMinutes.value ??
        (props.bookings[0] ? at(props.bookings[0].starts_at) : range.value[0]);
    scroller.value?.scrollTo({ top: Math.max(0, top(first) - 120) });
});
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

            <TimeAxis :range="range" :marks="hourMarks" :now="nowMinutes" />

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
                    v-for="([from, to], index) in hoursOf(member)"
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
                    v-if="nowMinutes !== null"
                    class="pointer-events-none absolute inset-x-0 z-10 h-px bg-destructive"
                    :style="{ top: `${top(nowMinutes)}px` }"
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
                        :class="statusEdge[booking.status]"
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
