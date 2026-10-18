<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';

/**
 * Money in (deposits, above the line) and out (refunds, below it), one
 * column per day. Bars stay thin with rounded ends; each day has a hover and
 * keyboard tooltip, and every value is also in the table view.
 */
type Day = { date: string; taken: number; refunded: number };

const props = defineProps<{
    days: Day[];
    currency: string;
}>();

const HEIGHT = 220;
const PAD = { top: 12, right: 8, bottom: 26, left: 52 };
const BAR_MAX = 24;
const RADIUS = 4;

const box = ref<HTMLElement | null>(null);
const width = ref(640);
const hovered = ref<number | null>(null);
const showTable = ref(false);
let observer: ResizeObserver | null = null;

onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        width.value = Math.max(280, entry.contentRect.width);
    });

    if (box.value) {
        observer.observe(box.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

/** 1, 2 or 5 times a power of ten, so ticks land on clean amounts. */
function niceStep(max: number, ticks: number): number {
    const raw = max / ticks;
    const power = 10 ** Math.floor(Math.log10(raw || 1));
    const unit = raw / power;

    return (unit <= 1 ? 1 : unit <= 2 ? 2 : unit <= 5 ? 5 : 10) * power;
}

const compact = new Intl.NumberFormat('en', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function axisMoney(minor: number): string {
    const symbol =
        new Intl.NumberFormat('en', {
            style: 'currency',
            currency: props.currency,
        })
            .formatToParts(0)
            .find((part) => part.type === 'currency')?.value ?? '';

    return `${minor < 0 ? '−' : ''}${symbol}${compact.format(Math.abs(minor) / 100)}`;
}

const scale = computed(() => {
    const maxIn = Math.max(0, ...props.days.map((d) => d.taken));
    const maxOut = Math.max(0, ...props.days.map((d) => d.refunded));
    const step = niceStep(Math.max(maxIn, maxOut, 100), 4);
    const top = Math.max(step, Math.ceil(maxIn / step) * step);
    // Leave a little room below the line even with no refunds, so it reads as a baseline.
    const bottom = maxOut === 0 ? step / 2 : Math.ceil(maxOut / step) * step;
    const plot = HEIGHT - PAD.top - PAD.bottom;
    const zero = PAD.top + (plot * top) / (top + bottom);
    const y = (amount: number) => zero - (amount / (top + bottom)) * plot;

    const ticks: number[] = [];

    for (
        let value = -Math.floor(bottom / step) * step;
        value <= top;
        value += step
    ) {
        ticks.push(value);
    }

    return { y, zero, ticks };
});

const band = computed(
    () => (width.value - PAD.left - PAD.right) / Math.max(1, props.days.length),
);
const barWidth = computed(() =>
    Math.max(2, Math.min(BAR_MAX, band.value * 0.6)),
);

function x(index: number): number {
    return PAD.left + band.value * index + band.value / 2;
}

/** A bar rounded only at its data end, square where it meets the line. */
function bar(index: number, amount: number, up: boolean): string {
    if (amount <= 0) {
        return '';
    }

    const w = barWidth.value;
    const left = x(index) - w / 2;
    const base = scale.value.zero;
    const end = scale.value.y(up ? amount : -amount);
    const length = Math.abs(end - base);
    const r = Math.min(RADIUS, w / 2, length);

    if (up) {
        return `M${left},${base} V${end + r} Q${left},${end} ${left + r},${end} H${left + w - r} Q${left + w},${end} ${left + w},${end + r} V${base} Z`;
    }

    return `M${left},${base} V${end - r} Q${left},${end} ${left + r},${end} H${left + w - r} Q${left + w},${end} ${left + w},${end - r} V${base} Z`;
}

// Date labels thin out so they never collide.
const labelEvery = computed(() => Math.max(1, Math.ceil(64 / band.value)));

const tooltip = computed(() => {
    if (hovered.value === null) {
        return null;
    }

    const day = props.days[hovered.value];
    const left = Math.min(Math.max(x(hovered.value), 90), width.value - 90);

    return { day, left };
});

const totalsByDay = computed(() =>
    props.days.filter((d) => d.taken > 0 || d.refunded > 0),
);
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-[2px] bg-money-in" />
                    Deposits taken
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-[2px] bg-money-out" />
                    Refunds
                </span>
            </div>
            <button
                type="button"
                class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                :aria-pressed="showTable"
                data-test="chart-table-toggle"
                @click="showTable = !showTable"
            >
                {{ showTable ? 'Show as chart' : 'Show as table' }}
            </button>
        </div>

        <div v-show="!showTable" ref="box" class="relative">
            <svg
                :width="width"
                :height="HEIGHT"
                class="block max-w-full"
                role="img"
                :aria-label="`Deposits and refunds per day, ${days.length} days`"
                data-test="money-chart"
            >
                <g>
                    <template v-for="tick in scale.ticks" :key="tick">
                        <line
                            :x1="PAD.left"
                            :x2="width - PAD.right"
                            :y1="scale.y(tick)"
                            :y2="scale.y(tick)"
                            :stroke-width="tick === 0 ? 1.5 : 1"
                            class="stroke-border"
                            :class="tick === 0 && 'stroke-muted-foreground/50'"
                        />
                        <text
                            :x="PAD.left - 8"
                            :y="scale.y(tick)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-muted-foreground text-[10px] tabular-nums"
                        >
                            {{ axisMoney(tick) }}
                        </text>
                    </template>
                </g>

                <g v-for="(day, index) in days" :key="day.date">
                    <path
                        :d="bar(index, day.taken, true)"
                        class="fill-money-in transition-opacity"
                        :class="
                            hovered !== null &&
                            hovered !== index &&
                            'opacity-40'
                        "
                    />
                    <path
                        :d="bar(index, day.refunded, false)"
                        class="fill-money-out transition-opacity"
                        :class="
                            hovered !== null &&
                            hovered !== index &&
                            'opacity-40'
                        "
                    />
                    <text
                        v-if="index % labelEvery === 0"
                        :x="x(index)"
                        :y="HEIGHT - 8"
                        text-anchor="middle"
                        class="fill-muted-foreground text-[10px]"
                    >
                        {{
                            formatDate(day.date, {
                                day: 'numeric',
                                month: 'short',
                            })
                        }}
                    </text>
                    <!-- The whole column is the hover and focus target, not just the bar. -->
                    <rect
                        :x="PAD.left + band * index"
                        :y="PAD.top"
                        :width="band"
                        :height="HEIGHT - PAD.top - PAD.bottom"
                        fill="transparent"
                        tabindex="0"
                        class="cursor-default outline-none"
                        :aria-label="`${formatDate(day.date, { weekday: 'long', day: 'numeric', month: 'long' })}: ${formatMoney(day.taken, currency)} taken, ${formatMoney(day.refunded, currency)} refunded`"
                        @pointerenter="hovered = index"
                        @pointerleave="hovered = null"
                        @focus="hovered = index"
                        @blur="hovered = null"
                    />
                </g>
            </svg>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute top-0 z-10 w-44 -translate-x-1/2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                :style="{ left: `${tooltip.left}px` }"
                role="status"
                data-test="chart-tooltip"
            >
                <p class="mb-1 text-muted-foreground">
                    {{
                        formatDate(tooltip.day.date, {
                            weekday: 'short',
                            day: 'numeric',
                            month: 'short',
                        })
                    }}
                </p>
                <p class="flex items-center justify-between gap-2">
                    <span
                        class="flex items-center gap-1.5 text-muted-foreground"
                    >
                        <span class="h-0.5 w-3 rounded bg-money-in" />Taken
                    </span>
                    <span class="font-semibold tabular-nums">{{
                        formatMoney(tooltip.day.taken, currency)
                    }}</span>
                </p>
                <p class="flex items-center justify-between gap-2">
                    <span
                        class="flex items-center gap-1.5 text-muted-foreground"
                    >
                        <span class="h-0.5 w-3 rounded bg-money-out" />Refunded
                    </span>
                    <span class="font-semibold tabular-nums">{{
                        formatMoney(tooltip.day.refunded, currency)
                    }}</span>
                </p>
                <p class="mt-1 flex justify-between gap-2 border-t pt-1">
                    <span class="text-muted-foreground">Net</span>
                    <span class="font-semibold tabular-nums">{{
                        formatMoney(
                            tooltip.day.taken - tooltip.day.refunded,
                            currency,
                        )
                    }}</span>
                </p>
            </div>
        </div>

        <div v-if="showTable" class="overflow-x-auto" data-test="chart-table">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b text-left text-xs text-muted-foreground"
                    >
                        <th class="py-2 font-medium">Day</th>
                        <th class="py-2 text-right font-medium">Taken</th>
                        <th class="py-2 text-right font-medium">Refunded</th>
                        <th class="py-2 text-right font-medium">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="day in totalsByDay"
                        :key="day.date"
                        class="border-b last:border-0"
                    >
                        <td class="py-2">
                            {{
                                formatDate(day.date, {
                                    weekday: 'short',
                                    day: 'numeric',
                                    month: 'short',
                                })
                            }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ formatMoney(day.taken, currency) }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ formatMoney(day.refunded, currency) }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{
                                formatMoney(day.taken - day.refunded, currency)
                            }}
                        </td>
                    </tr>
                    <tr v-if="totalsByDay.length === 0">
                        <td
                            colspan="4"
                            class="py-4 text-center text-muted-foreground"
                        >
                            No money in or out in this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
