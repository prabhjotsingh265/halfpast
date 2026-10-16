<script setup lang="ts">
// A clock face at half past: the right half is gone, the way a taken slot is.
withDefaults(defineProps<{ size?: number }>(), { size: 28 });
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 32 32"
        fill="none"
        role="img"
        aria-label="Halfpast"
    >
        <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="3" />
        <path d="M16 3.5a12.5 12.5 0 0 1 0 25z" fill="var(--sun)" />
        <path
            d="M16 3.5v25"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
        />
    </svg>
</template>
