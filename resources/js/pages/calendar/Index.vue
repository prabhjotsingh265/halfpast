<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookingList from '@/components/calendar/BookingList.vue';
import BookingSheet from '@/components/calendar/BookingSheet.vue';
import DayGrid from '@/components/calendar/DayGrid.vue';
import NewBookingSheet from '@/components/calendar/NewBookingSheet.vue';
import WeekGrid from '@/components/calendar/WeekGrid.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { zoneCity } from '@/lib/booking';
import { addDays, formatDate, toDate } from '@/lib/dates';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
    CalendarView,
    NewBookingOptions,
    NewBookingPreset,
} from '@/types/calendar';

const props = defineProps<{
    view: CalendarView;
    date: string;
    /** Every date shown: one for a day, Monday to Sunday for a week. */
    days: string[];
    today: string;
    timezone: string;
    /** The one person shown, or null for everyone. */
    person: number | null;
    team: { id: number; name: string }[];
    staff: CalendarStaff[];
    bookings: CalendarBooking[];
    timeOff: CalendarTimeOff[];
    newBooking: NewBookingOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Calendar', href: route('calendar') }],
    },
});

const views: { value: CalendarView; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'list', label: 'List' },
];

const loading = ref(false);
const selected = ref<CalendarBooking | null>(null);
const sheetOpen = ref(false);

const canBook = computed(() => props.newBooking.staff.length > 0);
const adding = ref(false);
const preset = ref<NewBookingPreset>({
    date: props.date,
    staffId: null,
    time: null,
});

const pad = (n: number) => String(n).padStart(2, '0');

/** Open the new-booking form, filled in from where it was asked for. */
function add(
    from: { date?: string; staffId?: number | null; minutes?: number } = {},
): void {
    if (!canBook.value) {
        return;
    }

    preset.value = {
        date: from.date ?? props.date,
        staffId: from.staffId ?? props.person,
        time:
            from.minutes === undefined
                ? null
                : `${pad(Math.floor(from.minutes / 60))}:${pad(from.minutes % 60)}`,
    };
    adding.value = true;
}

function go(
    change: { date?: string; view?: CalendarView; staff?: number | null } = {},
): void {
    const staff = 'staff' in change ? change.staff : props.person;

    router.get(
        route('calendar'),
        {
            view: change.view ?? props.view,
            date: change.date ?? props.date,
            ...(staff ? { staff } : {}),
        },
        {
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

const step = computed(() => (props.view === 'day' ? 1 : 7));
const unit = computed(() => (props.view === 'day' ? 'day' : 'week'));

function open(booking: CalendarBooking): void {
    selected.value = booking;
    sheetOpen.value = true;
}

// After an action the bookings reload: show the open one as it is now, or
// close the panel if it's gone from the calendar (cancelled).
watch(
    () => props.bookings,
    (bookings) => {
        if (!selected.value) {
            return;
        }

        const fresh = bookings.find((b) => b.id === selected.value?.id);

        if (fresh) {
            selected.value = fresh;
        } else {
            sheetOpen.value = false;
        }
    },
);

const range = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'long',
    timeZone: 'UTC',
});

const title = computed(() => {
    if (props.view === 'day') {
        return props.date === props.today
            ? `Today, ${formatDate(props.date, { day: 'numeric', month: 'long' })}`
            : formatDate(props.date, {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
              });
    }

    const week = range.formatRange(
        toDate(props.days[0]),
        toDate(props.days[props.days.length - 1]),
    );

    return props.days.includes(props.today) ? `This week, ${week}` : week;
});

const count = computed(() => {
    const booked = props.bookings.filter((b) => b.status !== 'held').length;

    return `${booked} ${booked === 1 ? 'booking' : 'bookings'}`;
});

const staffName = computed(() => {
    const id = selected.value?.staff_id;

    return (
        props.staff.find((m) => m.id === id)?.name ??
        props.team.find((m) => m.id === id)?.name ??
        null
    );
});
</script>

<template>
    <Head title="Calendar" />

    <div class="mx-auto w-full max-w-[100rem] space-y-4 p-4 md:p-6">
        <Heading
            title="Calendar"
            :description="`Times are ${zoneCity(timezone)} time.`"
        />

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-full"
                    :aria-label="`Previous ${unit}`"
                    @click="go({ date: addDays(date, -step) })"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-full"
                    :aria-label="`Next ${unit}`"
                    @click="go({ date: addDays(date, step) })"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>
            <Button
                v-if="!days.includes(today)"
                variant="outline"
                class="h-9 rounded-full"
                @click="go({ date: today })"
                >Today</Button
            >
            <h2 class="ml-1 text-lg font-semibold" data-test="calendar-title">
                {{ title }}
            </h2>
            <span class="text-sm text-muted-foreground" data-test="count"
                >· {{ count }}</span
            >

            <div
                class="flex w-full flex-wrap items-center gap-2 lg:ml-auto lg:w-auto"
            >
                <Button
                    v-if="canBook"
                    class="h-9 rounded-full"
                    data-test="new-booking-button"
                    @click="add()"
                >
                    <Plus class="size-4" />New booking
                </Button>
                <div
                    class="inline-flex h-9 items-center rounded-full border p-0.5"
                    role="group"
                    aria-label="View"
                >
                    <button
                        v-for="option in views"
                        :key="option.value"
                        type="button"
                        class="h-full rounded-full px-3.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            view === option.value
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="view === option.value"
                        :data-test="`view-${option.value}`"
                        @click="go({ view: option.value })"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <label v-if="team.length > 1 || person !== null">
                    <span class="sr-only">Whose calendar</span>
                    <select
                        :value="person ?? ''"
                        class="h-9 max-w-44 rounded-full border border-input bg-transparent px-3 text-sm"
                        data-test="person"
                        @change="
                            go({
                                staff:
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ) || null,
                            })
                        "
                    >
                        <option value="">Everyone</option>
                        <option
                            v-for="member in team"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                </label>
                <label class="ml-auto lg:ml-0">
                    <span class="sr-only">Go to a date</span>
                    <input
                        type="date"
                        :value="date"
                        class="h-9 rounded-full border border-input bg-transparent px-3 text-sm"
                        @change="
                            go({
                                date:
                                    ($event.target as HTMLInputElement).value ||
                                    today,
                            })
                        "
                    />
                </label>
            </div>
        </div>

        <div
            class="transition-opacity duration-200"
            :class="loading && 'opacity-50'"
        >
            <p
                v-if="staff.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Add someone to the team to see their bookings here.
            </p>
            <DayGrid
                v-else-if="view === 'day'"
                :key="`${date}-${person}`"
                :date="date"
                :today="today"
                :timezone="timezone"
                :staff="staff"
                :bookings="bookings"
                :time-off="timeOff"
                @open="open"
                @pick="(staffId, minutes) => add({ staffId, minutes })"
            />
            <WeekGrid
                v-else-if="view === 'week'"
                :key="`${days[0]}-${person}`"
                :days="days"
                :today="today"
                :timezone="timezone"
                :staff="staff"
                :bookings="bookings"
                :time-off="timeOff"
                @open="open"
                @day="(day) => go({ view: 'day', date: day })"
                @pick="(day, minutes) => add({ date: day, minutes })"
            />
            <BookingList
                v-else
                :days="days"
                :today="today"
                :timezone="timezone"
                :staff="staff"
                :bookings="bookings"
                :time-off="timeOff"
                @open="open"
            />
        </div>

        <BookingSheet
            v-model:open="sheetOpen"
            :booking="selected"
            :timezone="timezone"
            :staff-name="staffName"
        />

        <NewBookingSheet
            v-if="canBook"
            v-model:open="adding"
            :options="newBooking"
            :preset="preset"
            :timezone="timezone"
            :today="today"
        />
    </div>
</template>
