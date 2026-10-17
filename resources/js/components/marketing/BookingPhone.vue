<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import { daylight } from '@/lib/daylight';
import type { BusinessType, Currency } from '@/lib/marketing';
import { hash, money, sampleDeposit } from '@/lib/marketing';
import { clock12, clock24, countdown, duration } from '@/lib/time';

const props = defineProps<{
    name: string;
    type: BusinessType;
    currency: Currency;
}>();

const HOLD_SECONDS = 15 * 60;

const serviceIndex = ref(0);
const dayIndex = ref(0);
const held = ref<number | null>(null);
const holdEndsAt = ref(0);
const now = ref(Date.now());
const booked = ref<{ hour: number; day: string; service: string } | null>(null);
const isDark = ref(false);

const service = computed(
    () => props.type.services[serviceIndex.value] ?? props.type.services[0],
);
const deposit = computed(() => sampleDeposit(service.value.price));
const initial = computed(() => props.name.trim().charAt(0).toUpperCase());

const days = Array.from({ length: 6 }, (_, i) => {
    const date = new Date();
    date.setDate(date.getDate() + i + 1);

    return {
        short: date.toLocaleDateString('en-GB', { weekday: 'short' }),
        long: date.toLocaleDateString('en-GB', { weekday: 'long' }),
        date: date.getDate(),
    };
});

// Each business (and day) gets its own busy pattern, so typing a name
// visibly changes the page. Roughly a quarter of the times are taken.
const slots = computed(() => {
    const seed = hash(`${props.name}|${props.type.kind}|${dayIndex.value}`);

    return Array.from({ length: 17 }, (_, i) => {
        const hour = 9 + i / 2;
        const taken =
            ((seed >>> (i % 31)) & 1) + ((seed >>> ((i + 11) % 31)) & 1);

        return { hour, free: taken < 2 };
    });
});

const remaining = computed(() =>
    held.value === null
        ? 0
        : Math.max(0, (holdEndsAt.value - now.value) / 1000),
);

function pick(hour: number): void {
    held.value = hour;
    holdEndsAt.value = Date.now() + HOLD_SECONDS * 1000;
}

function pay(): void {
    if (held.value === null) {
        return;
    }

    booked.value = {
        hour: held.value,
        day: days[dayIndex.value].long,
        service: service.value.name,
    };
    held.value = null;
}

function bookAnother(): void {
    booked.value = null;
}

// A different business or day can make the held time unavailable.
watch(slots, (list) => {
    if (
        held.value !== null &&
        !list.some((s) => s.hour === held.value && s.free)
    ) {
        held.value = null;
    }
});

watch(
    () => props.type.kind,
    () => {
        serviceIndex.value = 0;
        held.value = null;
        booked.value = null;
    },
);

let timer = 0;

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    timer = window.setInterval(() => {
        now.value = Date.now();

        if (held.value !== null && remaining.value <= 0) {
            held.value = null;
        }
    }, 250);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <div
        class="relative mx-auto w-full max-w-[20rem] rounded-[2.9rem] border-[10px] border-[#15181F] bg-background shadow-[0_40px_80px_-30px_rgb(21_24_31/0.45)] dark:ring-1 dark:ring-white/15"
    >
        <div
            class="absolute top-2 left-1/2 z-20 h-6 w-24 -translate-x-1/2 rounded-full bg-[#15181F]"
            aria-hidden="true"
        />

        <div
            class="relative h-[38rem] [scrollbar-width:none] overflow-y-auto rounded-[2.2rem]"
        >
            <Transition name="screen" mode="out-in">
                <!-- Picking a time -->
                <div v-if="!booked" key="pick" class="px-4 pt-11 pb-28">
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-sun text-lg font-[800] text-[#15181F]"
                        >
                            {{ initial }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-[720]">{{ name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ type.description }} · open 9:00–17:30
                            </p>
                        </div>
                    </div>

                    <p
                        class="mt-4 text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Service
                    </p>
                    <div
                        class="mt-2 space-y-1.5"
                        role="radiogroup"
                        aria-label="Service"
                    >
                        <button
                            v-for="(item, index) in type.services"
                            :key="item.name"
                            type="button"
                            role="radio"
                            :aria-checked="serviceIndex === index"
                            class="flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left transition-[border-color,box-shadow]"
                            :class="
                                serviceIndex === index
                                    ? 'border-foreground shadow-[inset_0_0_0_1px_var(--ink)]'
                                    : 'hover:border-foreground/40'
                            "
                            @click="serviceIndex = index"
                        >
                            <span>
                                <span class="block text-sm font-semibold">{{
                                    item.name
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ duration(item.minutes) }} with
                                    {{ type.staff }}
                                </span>
                            </span>
                            <span class="text-sm font-semibold font-time">{{
                                money(item.price, currency)
                            }}</span>
                        </button>
                    </div>

                    <p
                        class="mt-4 text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Day
                    </p>
                    <div
                        class="mt-2 grid grid-cols-6 gap-1.5"
                        role="radiogroup"
                        aria-label="Day"
                    >
                        <button
                            v-for="(day, index) in days"
                            :key="day.long"
                            type="button"
                            role="radio"
                            :aria-checked="dayIndex === index"
                            class="flex flex-col items-center rounded-lg border py-1.5 transition-colors"
                            :class="
                                dayIndex === index
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'hover:bg-accent'
                            "
                            @click="dayIndex = index"
                        >
                            <span class="text-[0.65rem]">{{ day.short }}</span>
                            <span class="text-sm font-semibold font-time">{{
                                day.date
                            }}</span>
                        </button>
                    </div>

                    <p
                        class="mt-4 text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Time
                    </p>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            v-for="slot in slots"
                            :key="slot.hour"
                            type="button"
                            class="phone-slot relative h-10 rounded-lg border text-sm font-semibold"
                            :class="{
                                'is-held': held === slot.hour,
                                'is-taken': !slot.free,
                            }"
                            :style="{
                                backgroundColor: slot.free
                                    ? daylight(
                                          slot.hour,
                                          isDark ? 'dark' : 'light',
                                      )
                                    : undefined,
                            }"
                            :disabled="!slot.free"
                            :aria-pressed="held === slot.hour"
                            :aria-label="`${clock12(slot.hour)}, ${slot.free ? (held === slot.hour ? 'held for you' : 'available') : 'taken'}`"
                            @click="pick(slot.hour)"
                        >
                            <span class="slot-time">{{
                                clock24(slot.hour)
                            }}</span>
                            <HoldRing
                                v-if="held === slot.hour"
                                :progress="remaining / HOLD_SECONDS"
                                :offset="4"
                                :radius="8"
                            />
                        </button>
                    </div>
                </div>

                <!-- Booked -->
                <div
                    v-else
                    key="done"
                    class="flex h-full flex-col items-center justify-center px-6 pt-10 text-center"
                >
                    <svg
                        class="booked-check size-20"
                        viewBox="0 0 80 80"
                        aria-hidden="true"
                    >
                        <circle cx="40" cy="40" r="36" fill="var(--sun)" />
                        <path
                            d="M24 41l11 11 22-24"
                            fill="none"
                            stroke="#15181F"
                            stroke-width="6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            pathLength="1"
                        />
                    </svg>
                    <p class="mt-6 text-3xl font-[820] font-casual">Booked.</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        See you {{ booked.day }} at {{ clock12(booked.hour) }}.
                    </p>
                    <div
                        class="mt-6 w-full rounded-xl border p-3 text-left text-sm"
                    >
                        <p class="font-semibold">
                            {{ booked.service }} with {{ type.staff }}
                        </p>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Check class="size-4 text-success" />
                            {{ money(deposit, currency) }} deposit paid
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            We'll remind you a day before and two hours before.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl bg-sun py-2.5 text-sm font-semibold text-[#15181F]"
                        @click="bookAnother"
                    >
                        Book another time
                    </button>
                </div>
            </Transition>

            <!-- Hold bar -->
            <Transition name="bar">
                <div
                    v-if="!booked"
                    class="sticky bottom-0 -mt-24 border-t bg-background/95 px-4 pt-3 pb-5 backdrop-blur"
                >
                    <p class="text-xs" aria-live="polite">
                        <template v-if="held !== null">
                            <strong>Held for you</strong> ·
                            {{ clock12(held) }} ·
                            <span class="font-time">{{
                                countdown(remaining)
                            }}</span>
                            left
                        </template>
                        <template v-else
                            >Pick a time to hold it for 15 minutes.</template
                        >
                    </p>
                    <button
                        type="button"
                        class="mt-2 w-full rounded-xl py-2.5 text-sm font-semibold transition-[background-color,color,opacity]"
                        :class="
                            held !== null
                                ? 'bg-sun text-[#15181F]'
                                : 'bg-muted text-muted-foreground'
                        "
                        :disabled="held === null"
                        @click="pay"
                    >
                        Pay {{ money(deposit, currency) }} deposit
                    </button>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.phone-slot {
    border-color: color-mix(in oklch, var(--ink) 12%, transparent);
    transition: transform 140ms ease-out;
}

.phone-slot:not(:disabled):active {
    transform: scale(0.97);
}

.slot-time {
    display: inline-block;
    transition: --mono 450ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.phone-slot.is-held .slot-time {
    --mono: 1;
}

.phone-slot.is-taken {
    color: var(--ink-2);
    background-image: repeating-linear-gradient(
        135deg,
        transparent 0 5px,
        color-mix(in oklch, var(--ink) 7%, transparent) 5px 6px
    );
}

.phone-slot.is-taken .slot-time {
    text-decoration: line-through;
}

.booked-check circle {
    transform-origin: center;
    animation: pop 420ms cubic-bezier(0.2, 0.9, 0.3, 1.3);
}

.booked-check path {
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    animation: tick 360ms 220ms ease-out forwards;
}

@keyframes pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
}

@keyframes tick {
    to {
        stroke-dashoffset: 0;
    }
}

.screen-enter-active,
.screen-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.screen-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.screen-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

@media (prefers-reduced-motion: reduce) {
    .phone-slot,
    .slot-time,
    .screen-enter-active,
    .screen-leave-active {
        transition: none;
    }

    .booked-check circle,
    .booked-check path {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
