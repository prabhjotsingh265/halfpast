<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dateIn, hourIn, timeIn, zoneCity } from '@/lib/booking';
import { formatDate } from '@/lib/dates';
import type { CalendarTimeOff } from '@/types/calendar';

/**
 * A stretch of time off or a closure, opened from the calendar, with a way
 * to free the time up again.
 */
const props = defineProps<{
    entry: CalendarTimeOff | null;
    staffName: string | null;
    timezone: string;
    businessName: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const confirming = ref(false);
const busy = ref(false);

watch(
    () => props.entry?.id,
    () => (confirming.value = false),
);

const title = computed(() => {
    if (!props.entry) {
        return '';
    }

    return (
        props.entry.reason ??
        (props.entry.staff_id === null ? 'Closed' : 'Time off')
    );
});

const who = computed(() =>
    props.entry?.staff_id === null
        ? `${props.businessName} is closed`
        : `${props.staffName ?? 'Someone'} is away`,
);

/** "Mon 19 Oct, 1:00 pm – 2:00 pm", "Mon 19 Oct, all day", or a span of days. */
const when = computed(() => {
    const entry = props.entry;

    if (!entry) {
        return '';
    }

    const zone = props.timezone;
    const day = (instant: string) =>
        formatDate(dateIn(instant, zone), {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    const atMidnight = (instant: string) => hourIn(instant, zone) === 0;
    // The last moment of the time off, so a day ending at midnight shows itself.
    const lastDay = dateIn(
        new Date(new Date(entry.ends_at).getTime() - 1000),
        zone,
    );
    const allDays = atMidnight(entry.starts_at) && atMidnight(entry.ends_at);

    if (allDays) {
        const first = dateIn(entry.starts_at, zone);

        return first === lastDay
            ? `${day(entry.starts_at)}, all day`
            : `${day(entry.starts_at)} to ${formatDate(lastDay, { weekday: 'short', day: 'numeric', month: 'short' })}`;
    }

    return dateIn(entry.starts_at, zone) === dateIn(entry.ends_at, zone)
        ? `${day(entry.starts_at)}, ${timeIn(entry.starts_at, zone)} – ${timeIn(entry.ends_at, zone)}`
        : `${day(entry.starts_at)} ${timeIn(entry.starts_at, zone)} to ${day(entry.ends_at)} ${timeIn(entry.ends_at, zone)}`;
});

function remove(): void {
    if (!props.entry) {
        return;
    }

    router.delete(route('calendar.blocks.destroy', props.entry.id), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            class="w-full overflow-y-auto sm:max-w-md"
            data-test="time-off-sheet"
        >
            <template v-if="entry">
                <SheetHeader>
                    <SheetTitle class="text-xl">{{ title }}</SheetTitle>
                    <SheetDescription>{{ who }}</SheetDescription>
                </SheetHeader>

                <div class="space-y-5 px-4 pb-6">
                    <div
                        class="flex gap-3 rounded-xl bg-muted px-3 py-2.5 text-sm"
                    >
                        <CalendarOff
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <p>
                            {{ when }}
                            <span class="text-muted-foreground"
                                >· {{ zoneCity(timezone) }}</span
                            >
                        </p>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Online booking doesn't offer this time.
                    </p>

                    <section v-if="entry.can_remove" class="border-t pt-4">
                        <Button
                            v-if="!confirming"
                            variant="outline"
                            data-test="free-up"
                            @click="confirming = true"
                        >
                            Free up this time
                        </Button>
                        <div
                            v-else
                            class="space-y-3 rounded-xl border p-3"
                            data-test="free-up-confirm"
                        >
                            <p class="text-sm">
                                Remove it? Online booking will offer this time
                                again.
                            </p>
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="ghost"
                                    @click="confirming = false"
                                    >Keep it</Button
                                >
                                <Button
                                    :disabled="busy"
                                    data-test="free-up-yes"
                                    @click="remove"
                                    >Remove</Button
                                >
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
