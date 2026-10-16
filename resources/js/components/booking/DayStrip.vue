<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { addDays, formatDate } from '@/lib/dates';
import type { BookingDay } from '@/types/booking';

/**
 * A fortnight of days to pick from, each with how many times are free and
 * a small bar showing how open it is. Scrolls sideways on a phone.
 */
const props = defineProps<{
    days: BookingDay[];
    selected: string | null;
    today: string;
    lastDay: string;
    from: string;
}>();

const emit = defineEmits<{
    select: [date: string];
    page: [from: string];
}>();

const strip = ref<HTMLElement | null>(null);

const most = computed(() => Math.max(1, ...props.days.map((d) => d.free)));
const canGoBack = computed(() => props.from > props.today);
const canGoOn = computed(
    () => addDays(props.from, props.days.length) <= props.lastDay,
);

const range = computed(() => {
    if (props.days.length === 0) {
        return '';
    }

    const first = props.days[0].date;
    const last = props.days[props.days.length - 1].date;

    return `${formatDate(first, { day: 'numeric', month: 'short' })} – ${formatDate(last, { day: 'numeric', month: 'short' })}`;
});

// Keep the picked day in view when the strip scrolls sideways.
watch(
    () => props.selected,
    async (date) => {
        await nextTick();
        strip.value
            ?.querySelector<HTMLElement>(`[data-date="${date}"]`)
            ?.scrollIntoView({
                block: 'nearest',
                inline: 'nearest',
                behavior: 'smooth',
            });
    },
    { immediate: true },
);
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between gap-2">
            <p class="text-sm font-medium font-time">{{ range }}</p>
            <div class="flex gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="size-8 rounded-full"
                    :disabled="!canGoBack"
                    aria-label="Earlier days"
                    @click="emit('page', addDays(from, -days.length))"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="size-8 rounded-full"
                    :disabled="!canGoOn"
                    aria-label="Later days"
                    @click="emit('page', addDays(from, days.length))"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>

        <div
            ref="strip"
            class="-mx-4 flex snap-x snap-mandatory scroll-px-4 [scrollbar-width:none] gap-2 overflow-x-auto px-4 pb-2 md:mx-0 md:grid md:grid-cols-7 md:overflow-visible md:px-0"
            role="radiogroup"
            aria-label="Day"
        >
            <button
                v-for="day in days"
                :key="day.date"
                type="button"
                role="radio"
                :data-date="day.date"
                :aria-checked="selected === day.date"
                :aria-label="`${formatDate(day.date, { weekday: 'long', day: 'numeric', month: 'long' })}, ${day.free === 0 ? 'nothing free' : `${day.free} free times`}`"
                :disabled="day.free === 0"
                class="flex w-[4.25rem] shrink-0 snap-start flex-col items-center rounded-2xl border px-1 pt-2 pb-2.5 transition-[background-color,border-color,color] duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed md:w-auto"
                :class="
                    selected === day.date
                        ? 'border-foreground bg-foreground text-background'
                        : day.free === 0
                          ? 'border-dashed text-muted-foreground/60'
                          : 'bg-card hover:border-foreground/30'
                "
                @click="emit('select', day.date)"
            >
                <span class="text-[11px] font-medium">
                    {{
                        day.date === today
                            ? 'Today'
                            : formatDate(day.date, { weekday: 'short' })
                    }}
                </span>
                <span class="text-lg leading-tight font-semibold font-time">
                    {{ Number(day.date.slice(8)) }}
                </span>
                <span class="text-[10px] opacity-80">
                    {{ day.free === 0 ? 'Full' : `${day.free} free` }}
                </span>
                <span
                    class="mt-1.5 h-1 w-8 overflow-hidden rounded-full"
                    :class="
                        selected === day.date ? 'bg-background/25' : 'bg-muted'
                    "
                    aria-hidden="true"
                >
                    <span
                        class="block h-full rounded-full transition-[width] duration-500"
                        :class="
                            selected === day.date ? 'bg-sun' : 'bg-sun-deep/70'
                        "
                        :style="{ width: `${(day.free / most) * 100}%` }"
                    />
                </span>
            </button>
        </div>
    </div>
</template>
