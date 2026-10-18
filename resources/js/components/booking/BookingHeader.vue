<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Globe } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { hourIn, timeIn, zoneCity } from '@/lib/booking';
import { daylightRange } from '@/lib/daylight';

/**
 * The top of a business's booking pages: its initial and name over the
 * colour of the hour where the business is, beside a live clock for there.
 */
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const business = computed(() => usePage().props.business!);

const now = ref(new Date());
let timer = 0;

onMounted(() => {
    timer = window.setInterval(() => (now.value = new Date()), 20_000);
});

onBeforeUnmount(() => window.clearInterval(timer));

const tint = computed(() => {
    const hour = hourIn(now.value, business.value.timezone);

    return daylightRange(hour, hour + 4);
});
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
        class="relative overflow-clip border-b bg-[linear-gradient(120deg,var(--from),var(--to))] dark:bg-[linear-gradient(120deg,var(--from-dark),var(--to-dark))]"
        :style="tint"
    >
        <div
            class="mx-auto flex max-w-5xl items-center gap-4 px-4"
            :class="compact ? 'py-5' : 'py-8 md:py-12'"
        >
            <Link
                :href="route('booking.show', { business: business.slug })"
                class="grid shrink-0 animate-in place-items-center rounded-2xl bg-sun font-[820] text-[#15181F] shadow-sm duration-500 zoom-in-75"
                :class="
                    compact ? 'size-11 text-xl' : 'size-14 text-2xl md:size-16'
                "
                :aria-label="`${business.name} booking page`"
            >
                {{ business.name.trim().charAt(0).toUpperCase() }}
            </Link>
            <div class="min-w-0">
                <h1
                    class="animate-in truncate font-[760] tracking-tight duration-500 fade-in slide-in-from-bottom-2"
                    :class="compact ? 'text-xl' : 'text-2xl md:text-4xl'"
                >
                    {{ business.name }}
                </h1>
                <p
                    class="mt-1 flex items-center gap-1.5 text-sm text-foreground/70"
                >
                    <Globe class="size-3.5" />
                    It's
                    <span class="font-time">{{
                        timeIn(now, business.timezone)
                    }}</span>
                    in {{ zoneCity(business.timezone) }}
                </p>
            </div>
        </div>
    </header>
</template>
