<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { daylightRange } from '@/lib/daylight';
import { hash } from '@/lib/marketing';

/**
 * Initials on a daylight tint picked from the name, so each person keeps the
 * same colour wherever they appear.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'xs' | 'sm' | 'lg';
    }>(),
    { size: 'sm' },
);

const tint = computed(() => {
    const hour = 6 + (hash(props.name.trim() || '?') % 150) / 10;

    return daylightRange(hour, hour + 3);
});
</script>

<template>
    <span
        class="grid shrink-0 place-items-center rounded-full bg-[linear-gradient(135deg,var(--from),var(--to))] font-semibold ring-1 ring-black/5 ring-inset dark:bg-[linear-gradient(135deg,var(--from-dark),var(--to-dark))] dark:ring-white/10"
        :class="
            {
                xs: 'size-6 text-[10px]',
                sm: 'size-10 text-sm',
                lg: 'size-14 text-lg',
            }[size]
        "
        :style="tint"
        aria-hidden="true"
    >
        {{ getInitials(name) || '?' }}
    </span>
</template>
