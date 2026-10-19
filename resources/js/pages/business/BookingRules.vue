<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Rules = {
    hold_minutes: number;
    min_notice_minutes: number;
    booking_horizon_days: number;
    slot_step_minutes: number;
    cancellation_window_hours: number;
};

const props = defineProps<{
    rules: Rules;
    steps: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Business settings',
                href: route('business.profile.edit'),
            },
        ],
    },
});

const form = useForm<Rules>({ ...props.rules });

const NOTICE = [0, 15, 30, 60, 120, 240, 720, 1440, 2880, 10080];
const HORIZON = [7, 14, 30, 60, 90, 180, 365];
const WINDOW = [0, 2, 6, 12, 24, 48, 72, 168];

// Keep a value saved some other way selectable.
const withCurrent = (options: number[], value: number) =>
    options.includes(value)
        ? options
        : [...options, value].sort((a, b) => a - b);

function noticeLabel(minutes: number): string {
    if (minutes === 0) {
        return 'No notice';
    }

    if (minutes < 60) {
        return `${minutes} minutes`;
    }

    if (minutes < 1440) {
        const hours = minutes / 60;

        return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
    }

    const days = minutes / 1440;

    return `${days} ${days === 1 ? 'day' : 'days'}`;
}

function daysLabel(days: number): string {
    if (days === 365) {
        return 'A year';
    }

    return days % 7 === 0 && days < 30 ? `${days / 7} weeks` : `${days} days`;
}

function hoursLabel(hours: number): string {
    if (hours === 0) {
        return 'Any time before';
    }

    return hours % 24 === 0 && hours >= 48
        ? `${hours / 24} days`
        : `${hours} hours`;
}

const stepTimes = computed(() =>
    Array.from(
        { length: Math.min(4, 60 / form.slot_step_minutes) },
        (_, i) => `10:${String(i * form.slot_step_minutes).padStart(2, '0')}`,
    ).join(', '),
);

function submit(): void {
    form.put(route('business.rules.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Booking rules" />

    <form class="space-y-10" @submit.prevent="submit">
        <Heading
            variant="small"
            title="Booking rules"
            description="These apply to every service and everyone on the team."
        />

        <section
            class="grid gap-6 rounded-xl border bg-card p-5 sm:grid-cols-[1fr_auto] sm:items-center"
        >
            <div class="grid gap-2">
                <Label for="hold">How long a picked time is held</Label>
                <p class="text-sm text-muted-foreground">
                    While a customer enters their details and pays, nobody else
                    can take the time. Starting a payment gives them a fresh 15
                    minutes, up to 45 in all.
                </p>
                <input
                    id="hold"
                    v-model.number="form.hold_minutes"
                    type="range"
                    min="5"
                    max="30"
                    step="5"
                    class="mt-2 w-full accent-[var(--sun-deep)]"
                    aria-describedby="hold-value"
                />
                <InputError :message="form.errors.hold_minutes" />
            </div>
            <div
                class="relative mx-auto grid h-20 w-28 place-items-center rounded-2xl bg-sun/20"
                aria-hidden="true"
            >
                <span
                    id="hold-value"
                    class="text-xl font-semibold font-time"
                    data-test="hold-value"
                    >{{ form.hold_minutes }} min</span
                >
                <HoldRing
                    :progress="form.hold_minutes / 30"
                    :offset="5"
                    :radius="16"
                />
            </div>
        </section>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="notice">Minimum notice</Label>
                <select
                    id="notice"
                    v-model.number="form.min_notice_minutes"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option
                        v-for="minutes in withCurrent(
                            NOTICE,
                            form.min_notice_minutes,
                        )"
                        :key="minutes"
                        :value="minutes"
                    >
                        {{ noticeLabel(minutes) }}
                    </option>
                </select>
                <InputError :message="form.errors.min_notice_minutes" />
            </div>

            <div class="grid gap-2">
                <Label for="horizon">How far ahead</Label>
                <select
                    id="horizon"
                    v-model.number="form.booking_horizon_days"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option
                        v-for="days in withCurrent(
                            HORIZON,
                            form.booking_horizon_days,
                        )"
                        :key="days"
                        :value="days"
                    >
                        {{ daysLabel(days) }}
                    </option>
                </select>
                <InputError :message="form.errors.booking_horizon_days" />
            </div>

            <div class="grid gap-2">
                <Label for="step">Times start every</Label>
                <select
                    id="step"
                    v-model.number="form.slot_step_minutes"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option v-for="step in steps" :key="step" :value="step">
                        {{ step }} minutes
                    </option>
                </select>
                <InputError :message="form.errors.slot_step_minutes" />
            </div>

            <div class="grid gap-2">
                <Label for="window">Free cancellation until</Label>
                <select
                    id="window"
                    v-model.number="form.cancellation_window_hours"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option
                        v-for="hours in withCurrent(
                            WINDOW,
                            form.cancellation_window_hours,
                        )"
                        :key="hours"
                        :value="hours"
                    >
                        {{ hoursLabel(hours) }}
                    </option>
                </select>
                <InputError :message="form.errors.cancellation_window_hours" />
            </div>
        </div>

        <section
            class="rounded-xl bg-muted px-4 py-3 text-sm leading-relaxed"
            aria-live="polite"
            data-test="rules-summary"
        >
            <p class="mb-1 font-medium">What customers get</p>
            <p>
                They can book
                {{
                    form.min_notice_minutes === 0
                        ? 'right up to the start'
                        : `from ${noticeLabel(form.min_notice_minutes).toLowerCase()} ahead`
                }}
                to
                {{ daysLabel(form.booking_horizon_days).toLowerCase() }} ahead,
                at times like {{ stepTimes }}. A time they pick is theirs for
                {{ form.hold_minutes }} minutes while they finish.
                {{
                    form.cancellation_window_hours === 0
                        ? 'Cancelling any time before the appointment refunds the deposit.'
                        : `Cancelling at least ${hoursLabel(form.cancellation_window_hours)} before refunds the deposit; later than that, you keep it.`
                }}
            </p>
        </section>

        <div class="flex items-center gap-3">
            <Button
                type="submit"
                :disabled="form.processing || !form.isDirty"
                data-test="save-rules"
            >
                <Spinner v-if="form.processing" />
                Save rules
            </Button>
        </div>
    </form>
</template>
