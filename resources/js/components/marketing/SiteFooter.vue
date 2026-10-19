<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import HalfpastMark from '@/components/brand/HalfpastMark.vue';
</script>

<template>
    <footer class="border-t">
        <div
            class="mx-auto grid max-w-6xl gap-10 px-5 py-14 md:grid-cols-[1.4fr_1fr_1fr] md:px-8"
        >
            <div>
                <p class="flex items-center gap-2 text-lg font-[760]">
                    <HalfpastMark :size="26" />
                    Halfpast
                </p>
                <p class="mt-3 max-w-xs text-sm text-muted-foreground">
                    Booking pages for small businesses that take deposits and
                    never double-book.
                </p>
            </div>

            <nav aria-label="Product" class="text-sm">
                <p class="font-semibold">Product</p>
                <ul class="mt-3 space-y-2 text-muted-foreground">
                    <li>
                        <Link
                            :href="route('register')"
                            class="hover:text-foreground"
                            >Create a booking page</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="route('login')"
                            class="hover:text-foreground"
                            >Log in</Link
                        >
                    </li>
                </ul>
            </nav>

            <nav aria-label="Project" class="text-sm">
                <p class="font-semibold">Project</p>
                <ul class="mt-3 space-y-2 text-muted-foreground">
                    <li>
                        <a
                            href="https://github.com/prabhjotsingh265/halfpast"
                            class="hover:text-foreground"
                        >
                            Source code
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://github.com/prabhjotsingh265"
                            class="hover:text-foreground"
                        >
                            Built by Prabhjot Singh
                        </a>
                    </li>
                    <li>
                        <Link
                            :href="route('privacy')"
                            class="hover:text-foreground"
                            >Privacy</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="route('terms')"
                            class="hover:text-foreground"
                            >Terms</Link
                        >
                    </li>
                </ul>
            </nav>
        </div>
        <div class="border-t">
            <p
                class="mx-auto max-w-6xl px-5 py-5 text-xs text-muted-foreground md:px-8"
            >
                Halfpast is a portfolio project. Payments run in test mode, so
                no real money changes hands.
            </p>
        </div>
    </footer>
</template>
