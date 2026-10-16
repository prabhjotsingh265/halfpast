<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- The Halfpast mark: a clock face at half past, the right half taken. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="16"
            cy="16"
            r="14"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
        />
        <path d="M16 3.5a12.5 12.5 0 0 1 0 25z" fill="var(--sun)" />
        <path
            d="M16 3.5v25"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
        />
    </svg>
</template>
