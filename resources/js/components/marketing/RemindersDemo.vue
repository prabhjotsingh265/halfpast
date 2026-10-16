<script setup lang="ts">
import { BellRing } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

/**
 * Booked on Monday at 10:00 for Tuesday at 15:30: 29.5 hours apart. Drag the
 * timeline and each message arrives when its moment passes, exactly once,
 * however often the slider goes back and forth.
 */

const BOOKED_AT = 10; // Monday 10:00, as hours since Monday 00:00
const APPOINTMENT = 24 + 15.5; // Tuesday 15:30
const SPAN = APPOINTMENT - BOOKED_AT;

const messages = [
    {
        key: 'confirmation',
        at: 0,
        title: 'Booked',
        body: 'Haircut with Ben, Tue 3:30 pm. £10 deposit paid.',
    },
    {
        key: 'day',
        at: SPAN - 24,
        title: 'Tomorrow at 3:30 pm',
        body: 'Haircut with Ben at Glow Studio. Need to change it? Tap to manage.',
    },
    {
        key: 'hours',
        at: SPAN - 2,
        title: 'In 2 hours',
        body: 'Haircut with Ben at 3:30 pm. See you soon.',
    },
] as const;

const elapsed = ref(0);
const sent = ref<Record<string, string>>({ confirmation: 'Mon 10:00' });

function label(hoursSinceMonday: number): string {
    const day = hoursSinceMonday >= 24 ? 'Tue' : 'Mon';
    const h = hoursSinceMonday % 24;
    const minutes = Math.round(h * 60);

    return `${day} ${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

const now = computed(() => BOOKED_AT + elapsed.value);

// A message is sent the first time its moment passes, and never again.
watch(now, () => {
    for (const message of messages) {
        if (elapsed.value >= message.at && !sent.value[message.key]) {
            sent.value[message.key] = label(BOOKED_AT + message.at);
        }
    }
});

const delivered = computed(() =>
    messages
        .filter((m) => sent.value[m.key])
        .slice()
        .reverse(),
);
</script>

<template>
    <div class="grid gap-6 sm:grid-cols-[13rem_1fr] sm:items-center">
        <!-- Lock screen -->
        <div
            class="mx-auto h-[22rem] w-52 overflow-hidden rounded-[2rem] border-[7px] border-[#15181F] bg-linear-to-b from-daylight-09 to-daylight-15 px-2.5 pt-8"
        >
            <p class="text-center text-4xl font-[300] font-time">
                {{ label(now).slice(4) }}
            </p>
            <p class="text-center text-xs text-muted-foreground">
                {{ now >= 24 ? 'Tuesday' : 'Monday' }}
            </p>
            <TransitionGroup name="note" tag="ul" class="mt-4 space-y-1.5">
                <li
                    v-for="message in delivered"
                    :key="message.key"
                    class="rounded-xl bg-background/85 p-2 text-[0.68rem] leading-snug shadow-sm backdrop-blur"
                >
                    <p class="flex items-center gap-1 font-semibold">
                        <BellRing class="size-3" /> {{ message.title }}
                    </p>
                    <p class="text-muted-foreground">{{ message.body }}</p>
                </li>
            </TransitionGroup>
        </div>

        <div>
            <label for="reminder-time" class="text-sm font-semibold">
                Drag time forward from the booking to the appointment
            </label>
            <input
                id="reminder-time"
                v-model.number="elapsed"
                type="range"
                min="0"
                :max="SPAN"
                step="0.25"
                class="mt-3 w-full accent-[var(--sun-deep)]"
                :aria-valuetext="label(now)"
            />
            <div
                class="mt-1 flex justify-between text-xs text-muted-foreground font-time"
            >
                <span>Mon 10:00 · booked</span>
                <span>Tue 15:30 · appointment</span>
            </div>

            <ul class="mt-5 space-y-2 text-sm">
                <li
                    v-for="message in messages"
                    :key="message.key"
                    class="flex items-center gap-3"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full transition-colors"
                        :class="sent[message.key] ? 'bg-success' : 'bg-border'"
                    />
                    <span class="flex-1">{{ message.title }}</span>
                    <span class="text-xs text-muted-foreground font-time">
                        {{
                            sent[message.key]
                                ? `sent ${sent[message.key]}`
                                : 'waiting'
                        }}
                    </span>
                </li>
            </ul>
            <p class="mt-4 text-xs text-muted-foreground">
                Drag back and forth: each one still goes out once.
            </p>
        </div>
    </div>
</template>

<style scoped>
.note-enter-active {
    transition:
        opacity 260ms ease-out,
        transform 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.note-enter-from {
    opacity: 0;
    transform: translateY(-10px) scale(0.96);
}

@media (prefers-reduced-motion: reduce) {
    .note-enter-active {
        transition: none;
    }
}
</style>
