<script setup lang="ts">
import { ref, watch } from 'vue';
import BlockTimeForm from '@/components/calendar/BlockTimeForm.vue';
import NewBookingForm from '@/components/calendar/NewBookingForm.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { NewBookingOptions, NewBookingPreset } from '@/types/calendar';

/**
 * Adding to the calendar: a booking for someone who phoned or walked in, or
 * a stretch of blocked-out time. The forms mount afresh each time it opens.
 */
defineProps<{
    options: NewBookingOptions;
    preset: NewBookingPreset;
    timezone: string;
    today: string;
}>();

const open = defineModel<boolean>('open', { required: true });

type Mode = 'booking' | 'block';

const mode = ref<Mode>('booking');
const modes: { value: Mode; label: string }[] = [
    { value: 'booking', label: 'Booking' },
    { value: 'block', label: 'Block time' },
];

watch(open, (isOpen) => {
    if (isOpen) {
        mode.value = 'booking';
    }
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            class="w-full overflow-y-auto sm:max-w-md"
            data-test="add-sheet"
        >
            <SheetHeader>
                <SheetTitle class="text-xl">{{
                    mode === 'booking' ? 'New booking' : 'Block out time'
                }}</SheetTitle>
                <SheetDescription>
                    {{
                        mode === 'booking'
                            ? 'For someone who phoned or walked in. Confirmed straight away, with nothing to pay until the day.'
                            : 'Online booking stops offering this time. Bookings already in it stay booked.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <div
                class="mx-4 inline-flex h-9 w-fit items-center rounded-full border p-0.5"
                role="group"
                aria-label="What to add"
            >
                <button
                    v-for="option in modes"
                    :key="option.value"
                    type="button"
                    class="h-full rounded-full px-3.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        mode === option.value
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="mode === option.value"
                    :data-test="`mode-${option.value}`"
                    @click="mode = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <NewBookingForm
                v-if="mode === 'booking'"
                :options="options"
                :preset="preset"
                :timezone="timezone"
                :today="today"
                @done="open = false"
            />
            <BlockTimeForm
                v-else
                :options="options"
                :preset="preset"
                :timezone="timezone"
                :today="today"
                @done="open = false"
            />
        </SheetContent>
    </Sheet>
</template>
