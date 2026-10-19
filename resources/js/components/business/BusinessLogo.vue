<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A business's logo, or its initial on the sun colour until it has one.
 * Logos sit on a light tile in both themes, so a dark logo stays visible.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        logoUrl?: string | null;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        class?: string;
    }>(),
    { logoUrl: null, size: 'md', class: '' },
);

const sizes = {
    sm: 'size-8 rounded-lg text-sm',
    md: 'size-11 rounded-xl text-xl',
    lg: 'size-14 rounded-2xl text-2xl',
    xl: 'size-20 rounded-3xl text-4xl',
};

// Room around a logo, so it doesn't touch the tile's edge.
const padding = { sm: 'p-1', md: 'p-1.5', lg: 'p-2', xl: 'p-2.5' };

const initial = computed(
    () => props.name.trim().charAt(0).toUpperCase() || '?',
);
</script>

<template>
    <span
        :class="
            cn(
                'grid shrink-0 place-items-center overflow-hidden',
                sizes[size],
                logoUrl
                    ? ['border border-black/5 bg-white', padding[size]]
                    : 'bg-sun font-[820] text-[#15181F]',
                props.class,
            )
        "
    >
        <img
            v-if="logoUrl"
            :src="logoUrl"
            :alt="`${name} logo`"
            class="size-full object-contain"
        />
        <span v-else aria-hidden="true">{{ initial }}</span>
    </span>
</template>
