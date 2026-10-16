<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

defineProps<{
    eyebrow: string;
    title: string;
    text: string;
}>();

// Cards settle into place the first time they scroll into view.
const card = useTemplateRef<HTMLElement>('card');
const visible = ref(false);
let observer: IntersectionObserver | undefined;

onMounted(() => {
    if (!card.value || !('IntersectionObserver' in window)) {
        visible.value = true;

        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry?.isIntersecting) {
                visible.value = true;
                observer?.disconnect();
            }
        },
        { threshold: 0.15 },
    );
    observer.observe(card.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <article
        ref="card"
        class="feature-card rounded-2xl border bg-card p-5 shadow-sm md:p-8"
        :class="{ 'is-visible': visible }"
    >
        <p class="text-xs font-semibold tracking-wide text-sun-deep uppercase">
            {{ eyebrow }}
        </p>
        <h3 class="mt-2 text-2xl font-[780] font-casual md:text-3xl">
            {{ title }}
        </h3>
        <p class="mt-2 max-w-2xl text-muted-foreground">{{ text }}</p>
        <div class="mt-8">
            <slot />
        </div>
    </article>
</template>

<style scoped>
.feature-card {
    opacity: 0;
    transform: translateY(14px) scale(0.985);
    transition:
        opacity 500ms ease-out,
        transform 650ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.feature-card.is-visible {
    opacity: 1;
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .feature-card {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
