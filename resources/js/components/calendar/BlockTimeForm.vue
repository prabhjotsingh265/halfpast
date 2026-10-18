<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { zoneCity } from '@/lib/booking';
import type { NewBookingOptions, NewBookingPreset } from '@/types/calendar';

/**
 * Blocking out part of a day from the calendar: a lunch break, a meeting,
 * closing early. Online booking stops offering that time; bookings already
 * in it stay, and the message after saving says how many.
 */
const props = defineProps<{
    options: NewBookingOptions;
    preset: NewBookingPreset;
    timezone: string;
    today: string;
}>();

const emit = defineEmits<{ done: [] }>();

const REASONS = ['Lunch', 'Break', 'Meeting', 'Training'];

const pad = (n: number) => String(n).padStart(2, '0');

/** "13:30" plus an hour, stopping at the end of the day. */
function hourAfter(time: string): string {
    const [h, m] = time.split(':').map(Number);
    const minutes = Math.min(h * 60 + m + 60, 23 * 60 + 59);

    return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
}

const start = props.preset.time ?? '13:00';

const form = useForm({
    // Closing the whole business is never the default; it's picked on purpose.
    staff_id: (props.options.staff.find((m) => m.id === props.preset.staffId)
        ?.id ??
        props.options.staff[0]?.id ??
        null) as number | null,
    all_day: false,
    starts_on:
        props.preset.date < props.today ? props.today : props.preset.date,
    starts_time: start,
    ends_time: hourAfter(start),
    reason: '',
});

function submit(): void {
    // One day at a time from here; longer time off has its own page.
    form.transform((data) => ({ ...data, ends_on: data.starts_on })).post(
        route('calendar.blocks.store'),
        {
            preserveScroll: true,
            onSuccess: () => emit('done'),
        },
    );
}
</script>

<template>
    <form
        class="space-y-5 px-4 pb-6"
        data-test="block-time"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="bt-who">Who</Label>
            <select
                id="bt-who"
                v-model="form.staff_id"
                class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                data-test="bt-who"
            >
                <option
                    v-for="member in options.staff"
                    :key="member.id"
                    :value="member.id"
                >
                    {{ member.name }}
                </option>
                <option v-if="options.can_close" :value="null">
                    Everyone (the business is closed)
                </option>
            </select>
            <InputError :message="form.errors.staff_id" />
        </div>

        <div class="grid gap-2">
            <Label for="bt-date">Day</Label>
            <Input
                id="bt-date"
                v-model="form.starts_on"
                type="date"
                :min="today"
                data-test="bt-date"
            />
            <InputError :message="form.errors.starts_on" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <Checkbox v-model="form.all_day" data-test="bt-all-day" />
            All day
        </label>

        <div v-if="!form.all_day" class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="bt-from">From</Label>
                <Input
                    id="bt-from"
                    v-model="form.starts_time"
                    type="time"
                    step="300"
                    data-test="bt-from"
                />
            </div>
            <div class="grid gap-2">
                <Label for="bt-until">Until</Label>
                <Input
                    id="bt-until"
                    v-model="form.ends_time"
                    type="time"
                    step="300"
                    data-test="bt-until"
                />
            </div>
            <p class="col-span-2 -mt-1 text-xs text-muted-foreground">
                {{ zoneCity(timezone) }} time.
            </p>
            <InputError
                class="col-span-2"
                :message="form.errors.starts_time || form.errors.ends_time"
            />
        </div>

        <div class="grid gap-2">
            <Label for="bt-reason"
                >Reason
                <span class="font-normal text-muted-foreground"
                    >· optional</span
                ></Label
            >
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="reason in REASONS"
                    :key="reason"
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        form.reason === reason
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-muted'
                    "
                    :aria-pressed="form.reason === reason"
                    @click="form.reason = form.reason === reason ? '' : reason"
                >
                    {{ reason }}
                </button>
            </div>
            <Input
                id="bt-reason"
                v-model="form.reason"
                maxlength="120"
                placeholder="Or write your own"
                data-test="bt-reason"
            />
            <InputError :message="form.errors.reason" />
        </div>

        <div class="flex items-center justify-between gap-3 border-t pt-4">
            <Link
                v-if="options.can_close"
                :href="route('time-off.index')"
                class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >Away for longer?</Link
            >
            <span v-else />
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="bt-submit"
                >Block out time</Button
            >
        </div>
    </form>
</template>
