<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import BookingSheet from '@/components/calendar/BookingSheet.vue';
import DayGrid from '@/components/calendar/DayGrid.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { zoneCity } from '@/lib/booking';
import { addDays, formatDate } from '@/lib/dates';
import type {
    CalendarBooking,
    CalendarStaff,
    CalendarTimeOff,
} from '@/types/calendar';

const props = defineProps<{
    date: string;
    today: string;
    timezone: string;
    staff: CalendarStaff[];
    bookings: CalendarBooking[];
    timeOff: CalendarTimeOff[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Calendar', href: route('calendar') }],
    },
});

const loading = ref(false);
const selected = ref<CalendarBooking | null>(null);
const sheetOpen = ref(false);

function go(date: string): void {
    router.get(
        route('calendar'),
        { date },
        {
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

function open(booking: CalendarBooking): void {
    selected.value = booking;
    sheetOpen.value = true;
}

const title = computed(() =>
    props.date === props.today
        ? `Today, ${formatDate(props.date, { day: 'numeric', month: 'long' })}`
        : formatDate(props.date, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          }),
);

const counts = computed(() => {
    const confirmed = props.bookings.filter((b) => b.status !== 'held').length;

    return `${confirmed} ${confirmed === 1 ? 'booking' : 'bookings'}`;
});

const staffName = computed(
    () =>
        props.staff.find((m) => m.id === selected.value?.staff_id)?.name ??
        null,
);
</script>

<template>
    <Head title="Calendar" />

    <div class="mx-auto w-full max-w-[100rem] space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Calendar"
                :description="`Times are ${zoneCity(timezone)} time.`"
            />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-full"
                    aria-label="Previous day"
                    @click="go(addDays(date, -1))"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-full"
                    aria-label="Next day"
                    @click="go(addDays(date, 1))"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>
            <Button
                v-if="date !== today"
                variant="outline"
                class="h-9 rounded-full"
                @click="go(today)"
                >Today</Button
            >
            <h2 class="ml-1 text-lg font-semibold" data-test="calendar-title">
                {{ title }}
            </h2>
            <span class="text-sm text-muted-foreground">· {{ counts }}</span>
            <label class="ml-auto text-sm">
                <span class="sr-only">Go to a date</span>
                <input
                    type="date"
                    :value="date"
                    class="h-9 rounded-full border border-input bg-transparent px-3 text-sm"
                    @change="
                        go(($event.target as HTMLInputElement).value || today)
                    "
                />
            </label>
        </div>

        <div
            class="transition-opacity duration-200"
            :class="loading && 'opacity-50'"
        >
            <DayGrid
                v-if="staff.length > 0"
                :key="date"
                :date="date"
                :today="today"
                :timezone="timezone"
                :staff="staff"
                :bookings="bookings"
                :time-off="timeOff"
                @open="open"
            />
            <p
                v-else
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Add someone to the team to see their day here.
            </p>
        </div>

        <BookingSheet
            v-model:open="sheetOpen"
            :booking="selected"
            :timezone="timezone"
            :staff-name="staffName"
        />
    </div>
</template>
