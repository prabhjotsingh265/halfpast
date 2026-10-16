<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import HalfpastMark from '@/components/brand/HalfpastMark.vue';
import { Button } from '@/components/ui/button';

const page = usePage();
const scrolled = ref(false);

const onScroll = () => (scrolled.value = window.scrollY > 8);

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-50 border-b transition-[background-color,border-color,backdrop-filter] duration-300"
        :class="
            scrolled
                ? 'border-border bg-background/80 backdrop-blur-md'
                : 'border-transparent bg-transparent'
        "
    >
        <div
            class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3 md:px-8"
        >
            <Link
                :href="route('home')"
                class="flex items-center gap-2 text-lg font-[760]"
            >
                <HalfpastMark :size="26" />
                Halfpast
            </Link>

            <slot name="nav" />

            <div class="flex items-center gap-1 sm:gap-2">
                <Button v-if="page.props.auth.user" as-child size="sm">
                    <Link :href="route('dashboard')">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="route('register')">Start free</Link>
                    </Button>
                </template>
            </div>
        </div>
    </header>
</template>
