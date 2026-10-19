<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ExternalLink, MapPin, Phone } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BusinessLogo from '@/components/business/BusinessLogo.vue';
import WeekHours from '@/components/booking/WeekHours.vue';
import { timeIn, zoneCity } from '@/lib/booking';
import { daylight } from '@/lib/daylight';
import type { WeekHours as Hours } from '@/lib/openingHours';
import { clockHours, localDay, openStatus } from '@/lib/openingHours';

/**
 * The top of a business's booking page. Behind it, the business's day as
 * light: its colours from opening to closing time, with the sun where its
 * clock is now. In front: who it is, whether it's open, where to find it,
 * and its week's hours.
 */
const props = defineProps<{
    hours: Hours;
    /** The server's clock when the page was made, so both renders agree. */
    serverNow: string;
}>();

const business = computed(() => usePage().props.business!);

const now = ref(new Date(props.serverNow));
let timer = 0;

onMounted(() => {
    now.value = new Date();
    timer = window.setInterval(() => (now.value = new Date()), 30_000);
});

onBeforeUnmount(() => window.clearInterval(timer));

const zone = computed(() => business.value.timezone);
const status = computed(() => openStatus(props.hours, now.value, zone.value));

// The working day, from the earliest opening to the latest closing.
const day = computed(() => {
    const stretches = Object.values(props.hours).flat();
    const start = stretches.length
        ? Math.floor(Math.min(...stretches.map(([from]) => clockHours(from))))
        : 8;
    const end = stretches.length
        ? Math.ceil(Math.max(...stretches.map(([, to]) => clockHours(to))))
        : 20;

    return { start, end: Math.max(end, start + 4) };
});

const band = computed(() => {
    const { start, end } = day.value;
    const hours = Array.from({ length: end - start + 1 }, (_, i) => start + i);
    const stops = (mode: 'light' | 'dark') =>
        hours.map((hour) => daylight(hour, mode)).join(', ');

    return {
        '--day': `linear-gradient(100deg, ${stops('light')})`,
        '--day-dark': `linear-gradient(100deg, ${stops('dark')})`,
    };
});

// Where the sun sits: across the band as the business's day goes by.
const sun = computed(() => {
    const { start, end } = day.value;
    const hour = localDay(now.value, zone.value).hour;

    return Math.min(100, Math.max(0, ((hour - start) / (end - start)) * 100));
});

const hasHours = computed(() =>
    Object.values(props.hours).some((stretches) => stretches.length > 0),
);

const directions = computed(() =>
    business.value.address
        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(business.value.address)}`
        : null,
);
const phoneLink = computed(() =>
    business.value.phone
        ? `tel:${business.value.phone.replace(/[^0-9+]/g, '')}`
        : null,
);
const website = computed(() =>
    business.value.website?.replace(/^https?:\/\//i, '').replace(/\/$/, ''),
);
</script>

<template>
    <p
        v-if="business.is_demo"
        class="bg-foreground px-4 py-2 text-center text-xs text-background"
        data-test="demo-notice"
    >
        A demo business. Book anything you like: it's pretend, nothing is
        charged, and it all resets every night.
    </p>

    <header
        class="relative isolate overflow-clip border-b"
        :style="band"
        data-test="booking-hero"
    >
        <div
            class="hero-day absolute inset-0 -z-20 opacity-90 dark:opacity-70"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute top-1/2 -z-10 aspect-square w-[42rem] max-w-[120vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(255_255_255/0.75),transparent)] transition-[left] duration-[2000ms] ease-out dark:bg-[radial-gradient(closest-side,rgb(239_145_45/0.16),transparent)]"
            :style="{ left: `${sun}%` }"
            aria-hidden="true"
        />

        <div
            class="mx-auto grid max-w-5xl gap-8 px-4 py-10 md:py-14 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-center"
        >
            <div class="min-w-0">
                <BusinessLogo
                    :name="business.name"
                    :logo-url="business.logo_url"
                    size="xl"
                    class="animate-in shadow-sm duration-500 zoom-in-90"
                />
                <h1
                    class="mt-5 text-[clamp(2.25rem,6vw,3.5rem)] leading-[1.02] font-[820] tracking-tight font-casual"
                >
                    {{ business.name }}
                </h1>
                <p
                    v-if="business.description"
                    class="mt-3 max-w-xl text-base text-pretty text-foreground/75 md:text-lg"
                >
                    {{ business.description }}
                </p>

                <div
                    class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"
                >
                    <span
                        v-if="status"
                        class="inline-flex items-center gap-2 rounded-full border border-foreground/10 bg-background/75 px-3 py-1 font-medium backdrop-blur"
                        data-test="open-status"
                    >
                        <span class="relative flex size-2">
                            <span
                                v-if="status.open"
                                class="absolute inline-flex size-full rounded-full bg-success opacity-60 motion-safe:animate-ping"
                            />
                            <span
                                class="relative inline-flex size-2 rounded-full"
                                :class="
                                    status.open
                                        ? 'bg-success'
                                        : 'bg-muted-foreground'
                                "
                            />
                        </span>
                        {{ status.label }}
                    </span>
                    <span class="text-foreground/70">
                        It's
                        <time class="text-foreground font-time">{{
                            timeIn(now, zone)
                        }}</time>
                        in {{ zoneCity(zone) }}
                    </span>
                </div>

                <ul
                    v-if="business.address || business.phone || website"
                    class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-foreground/80"
                >
                    <li v-if="business.address">
                        <a
                            :href="directions!"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                        >
                            <MapPin class="size-4 shrink-0" />
                            {{ business.address }}
                        </a>
                    </li>
                    <li v-if="business.phone">
                        <a
                            :href="phoneLink!"
                            class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                        >
                            <Phone class="size-4 shrink-0" />
                            {{ business.phone }}
                        </a>
                    </li>
                    <li v-if="website">
                        <a
                            :href="business.website!"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                        >
                            <ExternalLink class="size-4 shrink-0" />
                            {{ website }}
                        </a>
                    </li>
                </ul>
            </div>

            <section
                v-if="hasHours"
                class="rounded-2xl border border-foreground/10 bg-background/75 p-4 shadow-sm backdrop-blur-md"
                aria-labelledby="opening-hours"
            >
                <h2
                    id="opening-hours"
                    class="mb-3 flex items-baseline justify-between text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Opening hours
                    <span class="font-normal tracking-normal normal-case"
                        >{{ zoneCity(zone) }} time</span
                    >
                </h2>
                <WeekHours :hours="hours" :zone="zone" :now="now" />
            </section>
        </div>
    </header>
</template>

<style scoped>
.hero-day {
    background: var(--day);
}

.dark .hero-day {
    background: var(--day-dark);
}
</style>
