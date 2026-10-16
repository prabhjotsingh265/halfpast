<script setup lang="ts">
import { Check, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import { Button } from '@/components/ui/button';

type Who = 'tom' | 'ana';
type State = 'idle' | 'sending' | 'won' | 'refused' | 'moved';

const TRAVEL_MS = 700;

const state = ref<Record<Who, State>>({ tom: 'idle', ana: 'idle' });
const winner = ref<Who | null>(null);
const timers: number[] = [];

const names: Record<Who, string> = { tom: 'Tom', ana: 'Ana' };
const later = (fn: () => void, ms: number) =>
    timers.push(window.setTimeout(fn, ms));

function arrive(who: Who): void {
    if (winner.value === null) {
        winner.value = who;
        state.value[who] = 'won';

        return;
    }

    // The slot is already booked, so the database refuses the second insert.
    state.value[who] = 'refused';
    later(() => (state.value[who] = 'moved'), 1100);
}

function tap(who: Who): void {
    if (state.value[who] !== 'idle') {
        return;
    }

    state.value[who] = 'sending';
    later(() => arrive(who), TRAVEL_MS);
}

function reset(): void {
    timers.splice(0).forEach((t) => window.clearTimeout(t));
    state.value = { tom: 'idle', ana: 'idle' };
    winner.value = null;
}

function both(): void {
    reset();
    // 40 ms apart: as close to "the same second" as two phones get.
    later(() => tap('tom'), 0);
    later(() => tap('ana'), 40);
}

onBeforeUnmount(reset);

const story = computed(() => {
    if (winner.value === null) {
        return 'Tap 3:30 for one customer, then the other. Or tap both at once.';
    }

    const lost: Who = winner.value === 'tom' ? 'ana' : 'tom';

    if (state.value[lost] === 'refused' || state.value[lost] === 'moved') {
        return `${names[winner.value]}'s request reached the database first. ${names[lost]}'s was refused by the database itself, not by a check a split second could slip past, and ${names[lost]} was offered 4:00 instead.`;
    }

    return `${names[winner.value]} has 3:30. Now tap for the other customer.`;
});

const statusText: Record<State, string> = {
    idle: 'Wants 3:30 with Ben',
    sending: 'Sending…',
    won: 'Booked 3:30',
    refused: 'Someone just took 3:30',
    moved: 'Booked 4:00 instead',
};

const colours: Record<Who, string> = { tom: 'var(--ink)', ana: 'var(--dusk)' };
</script>

<template>
    <div>
        <div class="flex flex-col items-stretch md:flex-row md:items-center">
            <template v-for="who in ['tom', 'ana'] as const" :key="who">
                <!-- Ana's lane comes before her card, so the slot sits between the two lanes. -->
                <template v-if="who === 'ana'">
                    <div
                        class="slot-box mx-auto w-full max-w-60 shrink-0 md:w-56"
                    >
                        <div
                            class="relative rounded-xl border-2 px-4 py-3 text-center transition-colors duration-300"
                            :class="
                                winner
                                    ? 'border-transparent bg-daylight-15'
                                    : 'border-dashed'
                            "
                            aria-live="polite"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Ben · Tuesday
                            </p>
                            <p class="mt-1 text-2xl font-[650] font-time">
                                3:30 pm
                            </p>
                            <p class="mt-1 h-5 text-sm">
                                <template v-if="winner">{{
                                    names[winner]
                                }}</template>
                                <span v-else class="text-muted-foreground"
                                    >Free</span
                                >
                            </p>
                            <HoldRing
                                v-if="winner"
                                :progress="1"
                                :offset="5"
                                :radius="12"
                            />
                        </div>
                    </div>
                    <div
                        class="lane lane-reverse mx-auto h-10 w-px shrink-0 md:h-px md:w-16"
                        :class="`is-${state[who]}`"
                        aria-hidden="true"
                    >
                        <span
                            class="dot"
                            :style="{ backgroundColor: colours[who] }"
                        />
                    </div>
                </template>

                <div
                    class="customer rounded-2xl border bg-card p-4 md:flex-1"
                    :class="{ 'is-refused': state[who] === 'refused' }"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-9 place-items-center rounded-full text-sm font-semibold text-white"
                            :style="{ backgroundColor: colours[who] }"
                        >
                            {{ names[who][0] }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold">{{ names[who] }}</p>
                            <p
                                class="truncate text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {{ statusText[state[who]] }}
                            </p>
                        </div>
                        <Check
                            v-if="
                                state[who] === 'won' || state[who] === 'moved'
                            "
                            class="ml-auto size-5 text-success"
                        />
                    </div>
                    <Button
                        class="mt-4 w-full"
                        variant="secondary"
                        :disabled="state[who] !== 'idle'"
                        @click="tap(who)"
                    >
                        Tap 3:30 as {{ names[who] }}
                    </Button>
                </div>

                <div
                    v-if="who === 'tom'"
                    class="lane mx-auto h-10 w-px shrink-0 md:h-px md:w-16"
                    :class="`is-${state[who]}`"
                    aria-hidden="true"
                >
                    <span
                        class="dot"
                        :style="{ backgroundColor: colours[who] }"
                    />
                </div>
            </template>
        </div>

        <div
            class="mt-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="max-w-2xl text-sm text-muted-foreground">{{ story }}</p>
            <div class="flex shrink-0 gap-2">
                <Button @click="both">Tap both at once</Button>
                <Button
                    variant="outline"
                    aria-label="Start again"
                    @click="reset"
                >
                    <RotateCcw class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* A dashed line; vertical on phones, horizontal from md up. */
.lane {
    position: relative;
    background: repeating-linear-gradient(
        to bottom,
        color-mix(in oklch, var(--ink) 25%, transparent) 0 4px,
        transparent 4px 8px
    );
}

/* The request dot. Tom's lane runs towards the slot (start → end); Ana's runs back (end → start). */
.dot {
    position: absolute;
    left: 50%;
    top: 0;
    width: 10px;
    height: 10px;
    margin: -5px 0 0 -5px;
    border-radius: 999px;
    opacity: 0;
    transition:
        top 700ms cubic-bezier(0.5, 0, 0.6, 1),
        left 700ms cubic-bezier(0.5, 0, 0.6, 1),
        opacity 150ms;
}

.lane-reverse .dot,
.lane.is-sending:not(.lane-reverse) .dot,
.lane.is-won:not(.lane-reverse) .dot {
    top: 100%;
}

.lane-reverse.is-sending .dot,
.lane-reverse.is-won .dot {
    top: 0;
}

.lane.is-sending .dot,
.lane.is-refused .dot {
    opacity: 1;
}

.lane.is-refused .dot {
    transition-timing-function: cubic-bezier(0.3, 1.6, 0.5, 1);
}

@media (min-width: 768px) {
    .lane {
        background: repeating-linear-gradient(
            to right,
            color-mix(in oklch, var(--ink) 25%, transparent) 0 4px,
            transparent 4px 8px
        );
    }

    .dot,
    .lane-reverse .dot,
    .lane-reverse.is-sending .dot,
    .lane-reverse.is-won .dot,
    .lane.is-sending:not(.lane-reverse) .dot,
    .lane.is-won:not(.lane-reverse) .dot {
        top: 50%;
    }

    .dot {
        left: 0;
    }

    .lane-reverse .dot,
    .lane.is-sending:not(.lane-reverse) .dot,
    .lane.is-won:not(.lane-reverse) .dot {
        left: 100%;
    }

    .lane-reverse.is-sending .dot,
    .lane-reverse.is-won .dot {
        left: 0;
    }
}

.customer.is-refused {
    animation: shake 420ms ease-out;
}

@keyframes shake {
    20% {
        transform: translateX(-6px);
    }
    40% {
        transform: translateX(5px);
    }
    60% {
        transform: translateX(-3px);
    }
    80% {
        transform: translateX(2px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .dot {
        transition: none;
    }

    .customer.is-refused {
        animation: none;
    }
}
</style>
