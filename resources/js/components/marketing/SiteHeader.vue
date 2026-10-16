<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import HalfpastMark from '@/components/brand/HalfpastMark.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

withDefaults(
    defineProps<{
        /** In-page sections, e.g. { label: 'Pricing', href: '#pricing' }. */
        links?: { label: string; href: string }[];
    }>(),
    { links: () => [] },
);

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

            <nav
                v-if="links.length"
                class="hidden items-center gap-7 text-sm md:flex"
            >
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="text-muted-foreground transition-colors hover:text-foreground"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div class="flex items-center gap-1 sm:gap-2">
                <Button v-if="page.props.auth.user" as-child size="sm">
                    <Link :href="route('dashboard')">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="hidden sm:inline-flex"
                    >
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="route('register')">Start free</Link>
                    </Button>
                </template>

                <Sheet v-if="links.length">
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="md:hidden"
                            aria-label="Open menu"
                        >
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" class="w-72 p-6">
                        <SheetTitle
                            class="flex items-center gap-2 text-lg font-[760]"
                        >
                            <HalfpastMark :size="24" />
                            Halfpast
                        </SheetTitle>
                        <nav class="mt-8 flex flex-col gap-1">
                            <SheetClose
                                v-for="link in links"
                                :key="link.href"
                                as-child
                            >
                                <a
                                    :href="link.href"
                                    class="rounded-lg px-3 py-2.5 text-base hover:bg-accent"
                                >
                                    {{ link.label }}
                                </a>
                            </SheetClose>
                        </nav>
                        <div
                            v-if="!page.props.auth.user"
                            class="mt-8 flex flex-col gap-2"
                        >
                            <Button as-child variant="outline">
                                <Link :href="route('login')">Log in</Link>
                            </Button>
                            <Button as-child>
                                <Link :href="route('register')"
                                    >Start free</Link
                                >
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </header>
</template>
