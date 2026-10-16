<script setup lang="ts">
import { computed } from 'vue';
import { clock24 as clock } from '@/lib/time';

const props = defineProps<{
    start: number;
    length: number;
    client?: string;
    service?: string;
    tint?: string;
    /** An empty slot someone is about to take: dashed outline, no fill. */
    pending?: boolean;
}>();

const range = computed(
    () => `${clock(props.start)}–${clock(props.start + props.length)}`,
);
</script>

<template>
    <div
        class="relative flex h-full flex-col justify-center rounded-md border px-2"
        :class="
            pending
                ? 'border-dashed border-black/25 dark:border-white/25'
                : 'border-black/10 dark:border-white/10'
        "
        :style="{ backgroundColor: pending ? 'transparent' : tint }"
    >
        <!-- Half-hour bookings only have room for one line. -->
        <p
            v-if="length <= 0.5"
            class="flex min-w-0 items-baseline gap-1.5 text-xs leading-none"
        >
            <span v-if="client" class="truncate font-semibold">{{
                client
            }}</span>
            <span
                class="shrink-0 text-[0.65rem] text-muted-foreground font-time"
            >
                {{ clock(start) }}
            </span>
        </p>
        <template v-else>
            <p class="truncate text-xs font-semibold">{{ client }}</p>
            <p class="truncate text-[0.65rem] text-muted-foreground font-time">
                <span class="hidden sm:inline">{{ range }}</span>
                <span class="sm:hidden">{{ clock(start) }}</span>
                · {{ service }}
            </p>
        </template>
        <slot />
    </div>
</template>
