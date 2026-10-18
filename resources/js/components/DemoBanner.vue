<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Across the top of the dashboard in a demo business: change anything,
 * it's all put back overnight.
 */
const page = usePage();

const show = computed(() => page.props.business?.is_demo === true);
const resetAt = computed(() => page.props.demoResetAt ?? '03:00');
</script>

<template>
    <p
        v-if="show"
        class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 bg-foreground px-4 py-2 text-center text-xs text-background"
        data-test="demo-banner"
    >
        <span
            >You're in a demo. Change anything you like: it all resets every
            night at {{ resetAt }} UTC.</span
        >
        <Link
            :href="route('register')"
            class="font-semibold underline underline-offset-4"
            >Set up your own business</Link
        >
    </p>
</template>
