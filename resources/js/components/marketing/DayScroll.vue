<script setup lang="ts">
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import HoldRing from '@/components/booking/HoldRing.vue';
import CalendarBlock from '@/components/marketing/CalendarBlock.vue';
import { daylight } from '@/lib/daylight';
import { clock24 } from '@/lib/time';

gsap.registerPlugin(ScrollTrigger);

type Slot = { staff: number; start: number; length: number };
type Booking = Slot & { client: string; service: string; bookedAt: number };

const DAY_START = 8;
const DAY_END = 20;
const HOURS = DAY_END - DAY_START;

const staff = [
    { name: 'Asha', role: 'Colour' },
    { name: 'Ben', role: 'Cuts' },
    { name: 'Chloé', role: 'Styling' },
];

// `bookedAt` is the time of day the booking comes in; `start` is when it happens.
const bookings: Booking[] = [
    {
        staff: 0,
        start: 9,
        length: 2,
        client: 'Maya R.',
        service: 'Balayage',
        bookedAt: 8.15,
    },
    {
        staff: 1,
        start: 9.5,
        length: 0.5,
        client: 'Sam K.',
        service: 'Cut',
        bookedAt: 8.4,
    },
    {
        staff: 2,
        start: 10,
        length: 1,
        client: 'Lena F.',
        service: 'Blow-dry',
        bookedAt: 8.8,
    },
    {
        staff: 1,
        start: 11,
        length: 0.5,
        client: 'Jon P.',
        service: 'Beard trim',
        bookedAt: 9.3,
    },
    {
        staff: 0,
        start: 12,
        length: 1,
        client: 'Priya S.',
        service: 'Gloss',
        bookedAt: 9.9,
    },
    {
        staff: 2,
        start: 13,
        length: 1.5,
        client: 'Erin M.',
        service: 'Bridal trial',
        bookedAt: 10.6,
    },
    {
        staff: 1,
        start: 14,
        length: 0.5,
        client: 'Dev A.',
        service: 'Cut',
        bookedAt: 11.3,
    },
    {
        staff: 0,
        start: 16,
        length: 1.5,
        client: 'Hana L.',
        service: 'Colour fix',
        bookedAt: 12.4,
    },
    {
        staff: 2,
        start: 17,
        length: 1,
        client: 'Rosa D.',
        service: 'Updo',
        bookedAt: 15.1,
    },
    {
        staff: 0,
        start: 18,
        length: 1,
        client: 'Kim T.',
        service: 'Gloss',
        bookedAt: 16.3,
    },
    {
        staff: 1,
        start: 18.5,
        length: 0.5,
        client: 'Leo B.',
        service: 'Cut',
        bookedAt: 17.2,
    },
    {
        staff: 2,
        start: 19,
        length: 0.5,
        client: 'Ava N.',
        service: 'Blow-dry',
        bookedAt: 18.1,
    },
];

// Two customers reach for Ben at 3:30 at the same moment, between 13:30 and 14:15.
const CONTEST_FROM = 13.5;
const CONTEST_TO = 14.25;
const winner: Slot = { staff: 1, start: 15.5, length: 0.5 };
const runnerUp: Slot = { staff: 1, start: 16, length: 0.5 };

const captions = [
    {
        at: 0,
        title: 'Doors open',
        body: 'The day starts empty. Bookings arrive while the salon gets on with its work.',
    },
    {
        at: 9.6,
        title: 'A busy morning',
        body: 'Every booking takes the colour of its hour, so the whole day reads at a glance.',
    },
    {
        at: 13.5,
        title: 'Two people, one slot',
        body: 'Both tap 3:30 with Ben in the same second. The database lets exactly one of them through.',
    },
    {
        at: 14.25,
        title: 'No double booking',
        body: 'The other customer is offered 4:00 before they have even noticed.',
    },
    {
        at: 16.5,
        title: 'Deposits do their job',
        body: 'A customer who has paid a deposit turns up.',
    },
    {
        at: 19.4,
        title: 'Bookings that show up.',
        body: 'Every hour, accounted for.',
    },
];

const reduced =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const progress = ref(reduced ? 0.55 : 0);
const hour = computed(() => DAY_START + HOURS * progress.value);
const isDark = ref(false);

const clamp01 = (n: number) => Math.min(1, Math.max(0, n));
const ease = (t: number) =>
    t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2;
const between = (t: number, from: number, to: number) =>
    clamp01((t - from) / (to - from));
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

const place = (slot: Slot) => ({
    top: `${top(slot.start)}%`,
    height: `${height(slot.length)}%`,
    left: `${(slot.staff / staff.length) * 100}%`,
    width: `${100 / staff.length}%`,
});

const tint = computed(() =>
    daylight(hour.value, isDark.value ? 'dark' : 'light'),
);
const slotTint = (start: number) =>
    daylight(start, isDark.value ? 'dark' : 'light');
const caption = computed(
    () =>
        [...captions].reverse().find((c) => hour.value >= c.at) ?? captions[0],
);

// Geometry, as percentages of the calendar body.
const top = (start: number) => ((start - DAY_START) / HOURS) * 100;
const height = (length: number) => (length / HOURS) * 100;
const columnCentre = (index: number) => ((index + 0.5) / staff.length) * 100;

const contest = computed(() => between(hour.value, CONTEST_FROM, CONTEST_TO));
const winnerCentre = {
    x: columnCentre(winner.staff),
    y: top(winner.start) + height(winner.length) / 2,
};
const runnerUpCentre = {
    x: columnCentre(runnerUp.staff),
    y: top(runnerUp.start) + height(runnerUp.length) / 2,
};

const cursorA = computed(() => {
    const t = ease(between(contest.value, 0, 0.4));

    return {
        x: lerp(14, winnerCentre.x - 4, t),
        y: lerp(94, winnerCentre.y, t),
    };
});

const cursorB = computed(() => {
    const reach = ease(between(contest.value, 0.05, 0.48));
    const bounce = between(contest.value, 0.48, 0.66);
    const move = ease(between(contest.value, 0.66, 0.9));
    const shake = Math.sin(bounce * Math.PI * 6) * 2.2 * (1 - bounce);
    const x = lerp(
        lerp(90, winnerCentre.x + 5, reach),
        runnerUpCentre.x + 5,
        move,
    );
    const y = lerp(lerp(6, winnerCentre.y + 1, reach), runnerUpCentre.y, move);

    return { x: x + shake, y };
});

const cursorsVisible = computed(
    () => hour.value > CONTEST_FROM - 0.1 && hour.value < CONTEST_TO + 0.6,
);
const ringDrawn = computed(() => ease(between(contest.value, 0.4, 0.56)));
const winnerBooked = computed(() => contest.value >= 0.6);
const toastVisible = computed(
    () => contest.value > 0.5 && contest.value < 0.92,
);
const runnerUpBooked = computed(() => contest.value >= 0.9);

const section = useTemplateRef<HTMLElement>('section');
let tween: gsap.core.Tween | undefined;

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');

    if (reduced || !section.value) {
        return;
    }

    const proxy = { p: 0 };

    tween = gsap.to(proxy, {
        p: 1,
        ease: 'none',
        onUpdate: () => {
            progress.value = proxy.p;
        },
        scrollTrigger: {
            trigger: section.value,
            start: 'top top',
            end: () => `+=${window.innerHeight * 3}`,
            pin: true,
            scrub: 0.6,
            invalidateOnRefresh: true,
        },
    });
});

onBeforeUnmount(() => {
    tween?.scrollTrigger?.kill();
    tween?.kill();
});
</script>

<template>
    <section
        ref="section"
        class="relative flex min-h-svh items-center overflow-clip"
        :style="{ backgroundColor: tint }"
        aria-label="A day at Glow Studio, told by scrolling"
    >
        <div
            class="mx-auto grid w-full max-w-6xl gap-4 px-5 pt-20 pb-6 md:px-8 lg:grid-cols-[1fr_1.35fr] lg:gap-14 lg:py-10"
        >
            <!-- The clock and what's happening -->
            <div class="flex flex-col justify-center">
                <p
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Glow Studio · Tuesday
                </p>
                <p
                    class="mt-1 text-[clamp(3.25rem,16vw,9.5rem)] leading-none font-[350] tracking-tight font-time lg:mt-2 lg:text-[clamp(4.5rem,13vw,9.5rem)]"
                    aria-live="off"
                >
                    {{ clock24(hour, 5) }}
                </p>
                <div class="mt-3 min-h-28 lg:mt-6 lg:min-h-36">
                    <Transition name="caption" mode="out-in">
                        <div :key="caption.title">
                            <h2
                                class="text-xl font-[780] font-casual md:text-3xl"
                            >
                                {{ caption.title }}
                            </h2>
                            <p
                                class="mt-1 max-w-sm text-sm text-muted-foreground md:mt-2 md:text-lg"
                            >
                                {{ caption.body }}
                            </p>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- The day, as the owner sees it -->
            <div
                class="rounded-xl border border-black/10 bg-background/70 p-3 shadow-sm backdrop-blur-sm md:p-4 dark:border-white/10"
            >
                <div class="grid grid-cols-[2.6rem_repeat(3,1fr)] gap-2 pb-3">
                    <span />
                    <div
                        v-for="person in staff"
                        :key="person.name"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-full bg-foreground text-xs font-semibold text-background"
                        >
                            {{ person.name[0] }}
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm font-semibold">{{
                                person.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                person.role
                            }}</span>
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-[2.6rem_1fr]">
                    <!-- Hour labels -->
                    <div class="relative h-[44svh] lg:h-[min(58svh,540px)]">
                        <span
                            v-for="h in HOURS"
                            :key="h"
                            class="absolute -translate-y-1/2 text-[0.68rem] text-muted-foreground font-time"
                            :style="{ top: `${top(DAY_START + h - 1)}%` }"
                        >
                            {{ String(DAY_START + h - 1).padStart(2, '0') }}:00
                        </span>
                    </div>

                    <div
                        class="relative h-[44svh] overflow-hidden rounded-lg border border-black/10 lg:h-[min(58svh,540px)] dark:border-white/10"
                    >
                        <!-- Hour lines and columns -->
                        <div
                            v-for="h in HOURS - 1"
                            :key="`line-${h}`"
                            class="absolute inset-x-0 border-t border-black/[0.06] dark:border-white/[0.06]"
                            :style="{ top: `${top(DAY_START + h)}%` }"
                        />
                        <div class="absolute inset-0 grid grid-cols-3">
                            <div
                                v-for="person in staff"
                                :key="`col-${person.name}`"
                                class="border-l border-black/[0.06] first:border-l-0 dark:border-white/[0.06]"
                            />
                        </div>

                        <!-- Bookings -->
                        <div
                            v-for="booking in bookings"
                            :key="`${booking.staff}-${booking.start}`"
                            class="booking absolute px-1 py-px"
                            :class="{ 'is-in': hour >= booking.bookedAt }"
                            :style="place(booking)"
                        >
                            <CalendarBlock
                                v-bind="booking"
                                :tint="slotTint(booking.start)"
                            />
                        </div>

                        <!-- The contested 3:30, and the 4:00 the second customer ends up with -->
                        <div class="absolute px-1 py-px" :style="place(winner)">
                            <CalendarBlock
                                v-bind="winner"
                                :client="winnerBooked ? 'Tom H.' : undefined"
                                :tint="slotTint(winner.start)"
                                :pending="!winnerBooked"
                            >
                                <HoldRing
                                    v-if="ringDrawn > 0 && contest < 1"
                                    :progress="ringDrawn"
                                    :offset="3"
                                    :radius="6"
                                />
                            </CalendarBlock>
                        </div>

                        <div
                            class="booking absolute px-1 py-px"
                            :class="{ 'is-in': runnerUpBooked }"
                            :style="place(runnerUp)"
                        >
                            <CalendarBlock
                                v-bind="runnerUp"
                                client="Ana P."
                                service="Cut"
                                :tint="slotTint(runnerUp.start)"
                            />
                        </div>

                        <!-- Now -->
                        <div
                            class="pointer-events-none absolute inset-x-0 z-10 flex items-center"
                            :style="{ top: `${progress * 100}%` }"
                            aria-hidden="true"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full bg-sun"
                            />
                            <span class="h-0.5 flex-1 bg-sun" />
                        </div>

                        <!-- Two customers, one slot -->
                        <template v-if="cursorsVisible">
                            <div
                                class="cursor absolute z-20"
                                :style="{
                                    left: `${cursorA.x}%`,
                                    top: `${cursorA.y}%`,
                                }"
                                aria-hidden="true"
                            >
                                <svg width="18" height="20" viewBox="0 0 18 20">
                                    <path
                                        d="M1 1l15 8-7 2-3 7z"
                                        fill="var(--ink)"
                                        stroke="var(--paper)"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                <span
                                    class="ml-3 rounded-full bg-foreground px-2 py-0.5 text-[0.65rem] font-semibold text-background"
                                    >Tom</span
                                >
                            </div>
                            <div
                                class="cursor absolute z-20"
                                :style="{
                                    left: `${cursorB.x}%`,
                                    top: `${cursorB.y}%`,
                                }"
                                aria-hidden="true"
                            >
                                <svg width="18" height="20" viewBox="0 0 18 20">
                                    <path
                                        d="M1 1l15 8-7 2-3 7z"
                                        fill="var(--dusk)"
                                        stroke="var(--paper)"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                <span
                                    class="ml-3 rounded-full bg-dusk px-2 py-0.5 text-[0.65rem] font-semibold text-white"
                                    >Ana</span
                                >
                            </div>
                        </template>

                        <Transition name="toast">
                            <div
                                v-if="toastVisible"
                                class="absolute inset-x-2 bottom-2 z-30 mx-auto max-w-xs rounded-lg border bg-background px-3 py-2 text-xs shadow-lg"
                                role="status"
                            >
                                <strong>Someone just took 3:30.</strong>
                                4:00 with Ben is still free.
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.booking {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
    transition:
        opacity 260ms ease-out,
        transform 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.booking.is-in {
    opacity: 1;
    transform: none;
}

.cursor {
    display: flex;
    align-items: flex-start;
}

.cursor > span {
    margin-top: 0.9rem;
    margin-left: -0.2rem;
}

.caption-enter-active,
.caption-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.caption-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.caption-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
    .booking,
    .caption-enter-active,
    .caption-leave-active,
    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }
}
</style>
