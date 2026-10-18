<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { daylightRange } from '@/lib/daylight';
import { hash } from '@/lib/marketing';

/**
 * Their photo, or else initials on a daylight tint picked from the name, so
 * each person keeps the same colour wherever they appear.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'xs' | 'sm' | 'lg';
        photo?: string | null;
    }>(),
    { size: 'sm', photo: null },
);

const sizes = {
    xs: 'size-6 text-[10px]',
    sm: 'size-10 text-sm',
    lg: 'size-14 text-lg',
};

const tint = computed(() => {
    const hour = 6 + (hash(props.name.trim() || '?') % 150) / 10;

    return daylightRange(hour, hour + 3);
});
</script>

<template>
    <img
        v-if="photo"
        :src="photo"
        alt=""
        class="shrink-0 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10"
        :class="sizes[size]"
        loading="lazy"
        decoding="async"
    />
    <span
        v-else
        class="grid shrink-0 place-items-center rounded-full bg-[linear-gradient(135deg,var(--from),var(--to))] font-semibold text-foreground ring-1 ring-black/5 ring-inset dark:bg-[linear-gradient(135deg,var(--from-dark),var(--to-dark))] dark:ring-white/10"
        :class="sizes[size]"
        :style="tint"
        aria-hidden="true"
    >
        {{ getInitials(name) || '?' }}
    </span>
</template>
