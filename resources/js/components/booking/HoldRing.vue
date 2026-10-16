<script setup lang="ts">
/**
 * The ring around a held slot. It sweeps clockwise from twelve o'clock like a
 * clock hand, and `progress` (0 to 1) is how much of the hold is left.
 */
withDefaults(
    defineProps<{
        progress: number;
        /** How far outside the slot the ring sits, in pixels. */
        offset?: number;
        /** The slot's corner radius, in pixels. */
        radius?: number;
    }>(),
    { offset: 5, radius: 10 },
);
</script>

<template>
    <span
        class="hold-ring"
        :style="{
            '--p': progress,
            inset: `-${offset}px`,
            borderRadius: `${radius + offset}px`,
        }"
        aria-hidden="true"
    />
</template>

<style scoped>
@property --p {
    syntax: '<number>';
    inherits: false;
    initial-value: 0;
}

.hold-ring {
    position: absolute;
    padding: 3px;
    pointer-events: none;
    background: conic-gradient(
        var(--sun) calc(var(--p) * 1turn),
        transparent 0
    );
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    mask-composite: exclude;
    transition: --p 160ms linear;
    animation: draw-in 520ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes draw-in {
    from {
        --p: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hold-ring {
        transition: none;
        animation: none;
    }
}
</style>
