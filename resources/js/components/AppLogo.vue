<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

// Inside the app, the business comes first; Halfpast sits underneath.
const title = computed(() => page.props.business?.name ?? page.props.name);
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center">
        <AppLogoIcon class="size-7 text-sidebar-foreground" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-semibold">{{ title }}</span>
        <span
            v-if="page.props.business"
            class="truncate text-xs leading-tight text-muted-foreground"
        >
            Halfpast
        </span>
    </div>
</template>
