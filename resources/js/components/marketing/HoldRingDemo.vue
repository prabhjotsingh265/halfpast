<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import { Button } from '@/components/ui/button';
import { daylight } from '@/lib/daylight';
import { clock12, countdown } from '@/lib/time';

const HOLD_SECONDS = 15 * 60;

type Slot = { label: string; hour: number; available: boolean };

const times: Slot[] = [];

for (let minutes = 9 * 60; minutes <= 17 * 60 + 30; minutes += 30) {
    const hour = minutes / 60;
    const label = `${Math.floor(hour)}:${String(minutes % 60).padStart(2, '0')}`;

    times.push({
        label,
        hour,
        available: !['10:00', '12:30', '13:00', '16:30'].includes(label),
    });
}

const days = Array.from({ length: 7 }, (_, i) => {
    const date = new Date();
    date.setDate(date.getDate() + i + 1);

    return {
        key: date.toDateString(),
        weekday: date.toLocaleDateString('en-GB', { weekday: 'short' }),
        day: date.getDate(),
    };
});

const selectedDay = ref(days[0].key);
const held = ref<string | null>(null);
const holdEndsAt = ref(0);
const now = ref(Date.now());
const speed = ref<1 | 60>(1);
const message = ref('Pick a time to hold it for 15 minutes while you pay.');
const shaking = ref<string | null>(null);

// 3:30 is the slot someone else grabs a second before you.
const takenByOthers = ref(new Set<string>());
const contested = '15:30';

const remaining = computed(() =>
    held.value ? Math.max(0, (holdEndsAt.value - now.value) / 1000) : 0,
);

// At 60× the demo runs a minute per second, so show the minutes the hold would really have.
const remainingLabel = computed(() => countdown(remaining.value * speed.value));

const isDark = ref(false);
const tint = (hour: number) => daylight(hour, isDark.value ? 'dark' : 'light');

const hourOf = (label: string) =>
    times.find((slot) => slot.label === label)?.hour ?? 0;
const to12h = (label: string) => clock12(hourOf(label));

function pick(slot: Slot): void {
    if (!slot.available || takenByOthers.value.has(slot.label)) {
        return;
    }

    if (slot.label === contested && !takenByOthers.value.has(contested)) {
        takenByOthers.value.add(contested);
        shaking.value = contested;
        held.value = null;
        message.value = 'Someone just took 3:30. 4:00 and 4:30 are still free.';
        window.setTimeout(() => (shaking.value = null), 450);

        return;
    }

    held.value = slot.label;
    holdEndsAt.value = Date.now() + (HOLD_SECONDS * 1000) / speed.value;
    message.value = '';
}

function setSpeed(value: 1 | 60): void {
    if (held.value) {
        const left = remaining.value * speed.value;
        holdEndsAt.value = Date.now() + (left * 1000) / value;
    }

    speed.value = value;
}

let frame = 0;

function tick(): void {
    now.value = Date.now();

    if (held.value && remaining.value <= 0) {
        message.value = `Your hold ran out, so ${to12h(held.value)} is free again for anyone.`;
        held.value = null;
    }

    frame = requestAnimationFrame(tick);
}

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    frame = requestAnimationFrame(tick);
});

onBeforeUnmount(() => cancelAnimationFrame(frame));

// The ring shows how much of the hold is left.
const ringProgress = computed(
    () => remaining.value / (HOLD_SECONDS / speed.value),
);
</script>

<template>
    <div class="grid gap-8 md:grid-cols-[15rem_1fr]">
        <div class="min-w-0">
            <p class="text-sm text-muted-foreground">Glow Studio</p>
            <h3 class="mt-1 text-xl font-semibold">Haircut with Ben</h3>
            <p class="mt-1 text-sm text-muted-foreground font-time">
                30 min · £30 · £10 deposit
            </p>

            <div
                class="mt-6 flex gap-2 overflow-x-auto pb-1"
                role="radiogroup"
                aria-label="Day"
            >
                <button
                    v-for="day in days"
                    :key="day.key"
                    type="button"
                    role="radio"
                    :aria-checked="selectedDay === day.key"
                    class="flex min-w-12 flex-col items-center rounded-lg border px-2 py-2 text-sm transition-colors"
                    :class="
                        selectedDay === day.key
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-accent'
                    "
                    @click="selectedDay = day.key"
                >
                    <span class="text-xs">{{ day.weekday }}</span>
                    <span class="font-semibold font-time">{{ day.day }}</span>
                </button>
            </div>

            <div
                class="mt-6 flex items-center gap-2 text-xs text-muted-foreground"
            >
                <span>Demo speed</span>
                <button
                    v-for="option in [1, 60] as const"
                    :key="option"
                    type="button"
                    class="rounded-full border px-2 py-0.5 font-time"
                    :class="
                        speed === option
                            ? 'border-foreground text-foreground'
                            : ''
                    "
                    @click="setSpeed(option)"
                >
                    {{ option }}×
                </button>
            </div>
        </div>

        <div class="min-w-0">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button
                    v-for="slot in times"
                    :key="slot.label"
                    type="button"
                    class="slot relative h-12 rounded-lg border text-sm font-semibold"
                    :class="{
                        'is-held': held === slot.label,
                        'is-shaking': shaking === slot.label,
                        'is-unavailable':
                            !slot.available || takenByOthers.has(slot.label),
                    }"
                    :style="{
                        backgroundColor:
                            slot.available && !takenByOthers.has(slot.label)
                                ? tint(slot.hour)
                                : undefined,
                    }"
                    :disabled="!slot.available || takenByOthers.has(slot.label)"
                    :aria-pressed="held === slot.label"
                    :aria-label="`${to12h(slot.label)}, ${!slot.available || takenByOthers.has(slot.label) ? 'taken' : held === slot.label ? 'held for you' : 'available'}`"
                    @click="pick(slot)"
                >
                    <span class="slot-time">{{ slot.label }}</span>
                    <HoldRing
                        v-if="held === slot.label"
                        :progress="ringProgress"
                    />
                </button>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p class="min-h-6 text-sm" aria-live="polite">
                    <template v-if="held">
                        <strong>Held for you</strong> ·
                        <span class="font-time">{{ remainingLabel }}</span> left
                    </template>
                    <template v-else>{{ message }}</template>
                </p>
                <Button :disabled="!held">Pay £10 deposit</Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slot {
    border-color: color-mix(in oklch, var(--ink) 12%, transparent);
    transition:
        transform 160ms ease-out,
        box-shadow 160ms ease-out;
}

.slot:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 0 color-mix(in oklch, var(--ink) 15%, transparent);
}

.slot-time {
    display: inline-block;
    transition: --mono 450ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.slot.is-held .slot-time {
    --mono: 1;
}

.slot.is-unavailable {
    color: var(--ink-2);
    background-image: repeating-linear-gradient(
        135deg,
        transparent 0 6px,
        color-mix(in oklch, var(--ink) 7%, transparent) 6px 7px
    );
    cursor: not-allowed;
}

.slot.is-unavailable .slot-time {
    text-decoration: line-through;
}

.slot.is-shaking {
    animation: shake 420ms ease-out;
}

@keyframes shake {
    20% {
        transform: translateX(-5px);
    }
    40% {
        transform: translateX(4px);
    }
    60% {
        transform: translateX(-3px);
    }
    80% {
        transform: translateX(2px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .slot,
    .slot-time {
        transition: none;
        animation: none;
    }

    .slot.is-shaking {
        animation: none;
    }
}
</style>
