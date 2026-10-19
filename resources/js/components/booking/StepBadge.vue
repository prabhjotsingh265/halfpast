<script setup lang="ts">
import { Check } from '@lucide/vue';

/** A step's number, turning into a tick in the sun colour once it's done. */
defineProps<{ step: number; done: boolean }>();
</script>

<template>
    <span
        class="relative grid size-7 shrink-0 place-items-center overflow-hidden rounded-full text-sm transition-colors duration-300"
        :class="
            done ? 'bg-sun text-[#15181F]' : 'bg-foreground text-background'
        "
    >
        <Transition
            mode="out-in"
            enter-from-class="scale-50 opacity-0"
            leave-to-class="scale-50 opacity-0"
            enter-active-class="transition duration-200 ease-out"
            leave-active-class="transition duration-150 ease-in"
        >
            <Check v-if="done" key="done" class="size-4" aria-hidden="true" />
            <span v-else key="step">{{ step }}</span>
        </Transition>
        <span v-if="done" class="sr-only">(done)</span>
    </span>
</template>
