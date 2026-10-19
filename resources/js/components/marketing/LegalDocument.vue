<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import SiteFooter from '@/components/marketing/SiteFooter.vue';
import SiteHeader from '@/components/marketing/SiteHeader.vue';
import { daylight } from '@/lib/daylight';

/**
 * The privacy policy and terms: a plain-language summary first, then the
 * sections, with a contents list that follows the reader down the page.
 */
const props = defineProps<{
    title: string;
    /** YYYY-MM-DD */
    updated: string;
    summary: string[];
    sections: { id: string; title: string }[];
}>();

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

// Spelled out by hand, so the server and the browser write it the same way.
const updatedOn = computed(() => {
    const [year, month, day] = props.updated.split('-').map(Number);

    return `${day} ${MONTHS[month - 1]} ${year}`;
});

// A whole working day across the top, dawn on the left to evening on the right.
const band = computed(() => {
    const hours = [6, 9, 12, 15, 18, 21];
    const stops = (mode: 'light' | 'dark') =>
        hours.map((hour) => daylight(hour, mode)).join(', ');

    return {
        '--day': `linear-gradient(100deg, ${stops('light')})`,
        '--day-dark': `linear-gradient(100deg, ${stops('dark')})`,
    };
});

const active = ref(props.sections[0]?.id ?? '');
const list = ref<HTMLElement | null>(null);
const marker = ref({ top: 0, height: 0 });
let frame = 0;

function moveMarker(): void {
    const link = list.value?.querySelector<HTMLElement>(
        `[data-section="${active.value}"]`,
    );

    if (link) {
        marker.value = { top: link.offsetTop, height: link.offsetHeight };
    }
}

// The section being read is the last one whose heading has passed the top
// of the screen; at the very bottom, it's the last section.
function findActive(): void {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
        const atBottom =
            window.innerHeight + window.scrollY >=
            document.documentElement.scrollHeight - 2;
        const passed = props.sections.filter(
            ({ id }) =>
                (document.getElementById(id)?.getBoundingClientRect().top ??
                    Infinity) <= 140,
        );
        const current = atBottom
            ? props.sections[props.sections.length - 1]
            : passed[passed.length - 1];

        active.value = current?.id ?? props.sections[0]?.id ?? '';
        void nextTick(moveMarker);
    });
}

onMounted(() => {
    findActive();
    window.addEventListener('scroll', findActive, { passive: true });
    window.addEventListener('resize', findActive, { passive: true });
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    window.removeEventListener('scroll', findActive);
    window.removeEventListener('resize', findActive);
});
</script>

<template>
    <Head :title="title" />

    <SiteHeader />

    <main>
        <header
            class="relative isolate overflow-clip border-b pt-28 pb-12 md:pt-36 md:pb-16"
            :style="band"
        >
            <div
                class="legal-band absolute inset-0 -z-10 opacity-80 motion-safe:animate-[legal-drift_36s_ease-in-out_infinite_alternate] dark:opacity-100"
                aria-hidden="true"
            />
            <div class="mx-auto max-w-6xl px-5 md:px-8">
                <p
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    The small print, in plain words
                </p>
                <h1
                    class="mt-3 text-4xl font-[820] tracking-tight font-casual md:text-6xl"
                >
                    {{ title }}
                </h1>
                <p class="mt-4 text-sm text-muted-foreground">
                    Last updated
                    <time :datetime="updated" class="font-time">{{
                        updatedOn
                    }}</time>
                </p>
            </div>
        </header>

        <div
            class="mx-auto grid max-w-6xl gap-12 px-5 py-12 md:px-8 md:py-16 lg:grid-cols-[13rem_minmax(0,1fr)]"
        >
            <nav aria-label="On this page" class="hidden lg:block">
                <div class="sticky top-24">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        On this page
                    </p>
                    <div class="relative mt-4 border-l text-sm">
                        <span
                            class="absolute top-0 -left-px w-0.5 rounded-full bg-sun transition-[transform,height] duration-300 ease-out"
                            :style="{
                                transform: `translateY(${marker.top}px)`,
                                height: `${marker.height}px`,
                            }"
                            aria-hidden="true"
                        />
                        <ol ref="list">
                            <li
                                v-for="section in sections"
                                :key="section.id"
                                :data-section="section.id"
                            >
                                <a
                                    :href="`#${section.id}`"
                                    class="block py-1.5 pl-4 transition-colors"
                                    :class="
                                        active === section.id
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    :aria-current="
                                        active === section.id
                                            ? 'true'
                                            : undefined
                                    "
                                    >{{ section.title }}</a
                                >
                            </li>
                        </ol>
                    </div>
                </div>
            </nav>

            <article class="legal min-w-0">
                <aside class="rounded-2xl border bg-muted/50 p-6 md:p-8">
                    <h2 class="!mt-0 !text-lg">The short version</h2>
                    <ul>
                        <li v-for="point in summary" :key="point">
                            {{ point }}
                        </li>
                    </ul>
                </aside>

                <slot />
            </article>
        </div>
    </main>

    <SiteFooter />
</template>

<style>
.legal-band {
    background: var(--day);
    background-size: 160% 100%;
}

.dark .legal-band {
    background: var(--day-dark);
    background-size: 160% 100%;
}

@keyframes legal-drift {
    from {
        background-position: 0% 50%;
    }
    to {
        background-position: 100% 50%;
    }
}

.legal {
    max-width: 68ch;
    color: var(--muted-foreground);
    line-height: 1.7;
}

.legal h2 {
    margin-top: 3.5rem;
    scroll-margin-top: 6rem;
    color: var(--foreground);
    font-size: 1.5rem;
    font-weight: 760;
    letter-spacing: -0.01em;
}

.legal h3 {
    margin-top: 2rem;
    color: var(--foreground);
    font-size: 1.0625rem;
    font-weight: 700;
}

.legal p,
.legal ul,
.legal table {
    margin-top: 1rem;
}

.legal ul {
    padding-left: 1.25rem;
    list-style: disc;
}

.legal li + li {
    margin-top: 0.5rem;
}

.legal li::marker {
    color: var(--sun);
}

.legal strong {
    color: var(--foreground);
    font-weight: 650;
}

.legal a {
    color: var(--foreground);
    text-decoration: underline;
    text-decoration-color: var(--sun);
    text-underline-offset: 3px;
}

.legal table {
    display: block;
    overflow-x: auto;
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9375rem;
}

.legal th,
.legal td {
    padding: 0.625rem 1rem 0.625rem 0;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
}

.legal th {
    color: var(--foreground);
    font-weight: 650;
    white-space: nowrap;
}

.legal td:first-child code {
    white-space: nowrap;
}

.legal code {
    font-variation-settings:
        'MONO' 1,
        'CASL' 0;
    color: var(--foreground);
    font-size: 0.875em;
}
</style>
