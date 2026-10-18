<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { dateIn, hourIn, minutesOnDay, timeIn } from '@/lib/booking';
import { statusLabel } from '@/lib/calendar';
import { formatDate } from '@/lib/dates';
import { daylight } from '@/lib/daylight';
import { formatMoney } from '@/lib/money';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
} from '@/types/calendar';

/**
 * The week as a list, day by day: easier than a grid on a phone, and
 * quicker to scan for who's coming in. Time off and closures sit among the
 * bookings, in time order.
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
    off: [entry: CalendarTimeOff];
}>();

const onePerson = computed(() => props.staff.length === 1);
const names = computed(
    () => new Map(props.staff.map((member) => [member.id, member.name])),
);

type Entry =
    | { kind: 'booking'; at: number; booking: CalendarBooking }
    | { kind: 'off'; at: number; off: CalendarTimeOff; when: string };

function offWhen(off: CalendarTimeOff, day: string): string {
    const from = minutesOnDay(off.starts_at, day, props.timezone);
    const to = minutesOnDay(off.ends_at, day, props.timezone);

    if (from === 0 && to === 24 * 60) {
        return 'All day';
    }

    if (from === 0) {
        return `Until ${timeIn(off.ends_at, props.timezone)}`;
    }

    if (to === 24 * 60) {
        return `From ${timeIn(off.starts_at, props.timezone)}`;
    }

    return `${timeIn(off.starts_at, props.timezone)} – ${timeIn(off.ends_at, props.timezone)}`;
}

const entries = computed(() =>
    props.days.map((day) => {
        const bookings = props.bookings.filter(
            (b) => dateIn(b.starts_at, props.timezone) === day,
        );
        const items: Entry[] = [
            ...bookings.map((booking) => ({
                kind: 'booking' as const,
                at: new Date(booking.starts_at).getTime(),
                booking,
            })),
            ...props.timeOff
                .filter(
                    (off) =>
                        minutesOnDay(off.ends_at, day, props.timezone) >
                        minutesOnDay(off.starts_at, day, props.timezone),
                )
                .map((off) => ({
                    kind: 'off' as const,
                    at: new Date(off.starts_at).getTime(),
                    off,
                    when: offWhen(off, day),
                })),
        ].sort((a, b) => a.at - b.at);

        return { day, count: bookings.length, items };
    }),
);

function offLabel(off: CalendarTimeOff): string {
    if (off.staff_id === null) {
        return 'Closed';
    }

    return onePerson.value
        ? 'Off'
        : `${names.value.get(off.staff_id) ?? 'Someone'} is off`;
}

function tintFor(booking: CalendarBooking) {
    const hour = hourIn(booking.starts_at, props.timezone);

    return { '--tint': daylight(hour), '--tint-dark': daylight(hour, 'dark') };
}
</script>

<template>
    <div class="space-y-6" data-test="booking-list">
        <section
            v-for="{ day, count, items } in entries"
            :key="day"
            :aria-labelledby="`list-${day}`"
            :data-test="`list-day-${day}`"
        >
            <h3
                :id="`list-${day}`"
                class="mb-2 flex flex-wrap items-baseline gap-x-2 text-sm"
            >
                <span class="font-semibold">{{
                    formatDate(day, {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    })
                }}</span>
                <Badge v-if="day === today" class="self-center">Today</Badge>
                <span class="text-muted-foreground">
                    {{
                        count === 0
                            ? 'Nothing booked'
                            : `${count} ${count === 1 ? 'booking' : 'bookings'}`
                    }}
                </span>
            </h3>

            <ul
                v-if="items.length > 0"
                class="divide-y overflow-hidden rounded-xl border bg-card"
            >
                <li
                    v-for="item in items"
                    :key="
                        item.kind === 'booking'
                            ? `b-${item.booking.id}`
                            : `o-${item.off.id}`
                    "
                >
                    <button
                        v-if="item.kind === 'booking'"
                        type="button"
                        class="flex w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none sm:px-4"
                        :data-test="`booking-${item.booking.code}`"
                        @click="emit('open', item.booking)"
                    >
                        <span
                            class="w-[5.5rem] shrink-0 rounded-md bg-[var(--tint)] px-2 py-1 text-xs tabular-nums dark:bg-[var(--tint-dark)]"
                            :style="tintFor(item.booking)"
                        >
                            <span class="block font-semibold">{{
                                timeIn(item.booking.starts_at, timezone)
                            }}</span>
                            <span class="block text-foreground/70">{{
                                timeIn(item.booking.ends_at, timezone)
                            }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{
                                item.booking.service_name
                            }}</span>
                            <span
                                class="block truncate text-sm text-muted-foreground"
                            >
                                {{ item.booking.customer?.name ?? 'Walk-in'
                                }}<template v-if="!onePerson">
                                    · with
                                    {{
                                        names.get(item.booking.staff_id ?? 0) ??
                                        'anyone'
                                    }}</template
                                >
                            </span>
                        </span>
                        <span
                            class="flex shrink-0 flex-col items-end gap-1 text-right"
                        >
                            <Badge
                                v-if="item.booking.status !== 'confirmed'"
                                :variant="
                                    item.booking.status === 'no_show'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                                >{{ statusLabel[item.booking.status] }}</Badge
                            >
                            <span class="text-sm tabular-nums">{{
                                formatMoney(
                                    item.booking.price,
                                    item.booking.currency,
                                )
                            }}</span>
                            <span
                                v-if="item.booking.paid"
                                class="text-xs text-success"
                                >Deposit paid</span
                            >
                        </span>
                    </button>
                    <button
                        v-else
                        type="button"
                        class="flex w-full items-center gap-3 bg-[repeating-linear-gradient(135deg,transparent_0_6px,color-mix(in_oklch,var(--ink)_6%,transparent)_6px_7px)] px-3 py-2.5 text-left text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none sm:px-4"
                        :data-test="`off-${item.off.id}`"
                        @click="emit('off', item.off)"
                    >
                        <span class="w-[5.5rem] shrink-0 text-xs">{{
                            item.when
                        }}</span>
                        <span class="min-w-0 flex-1 truncate">
                            {{ offLabel(item.off)
                            }}<template v-if="item.off.reason">
                                · {{ item.off.reason }}</template
                            >
                        </span>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
