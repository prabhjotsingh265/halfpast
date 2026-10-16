<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="px-5 pb-24 md:px-8">
        <!-- The whole day, dawn to evening, as one strip. -->
        <div class="mx-auto max-w-6xl overflow-hidden rounded-3xl border">
            <div
                class="bg-linear-to-r from-daylight-06 via-daylight-12 to-daylight-21 px-6 py-14 text-center md:px-12 md:py-20"
            >
                <h2
                    class="mx-auto max-w-3xl text-4xl font-[820] font-casual md:text-6xl"
                >
                    Your first booking could come in tonight.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-muted-foreground">
                    Set up your services and hours, share the link, and the rest
                    runs itself.
                </p>
                <div
                    class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Button as-child size="lg">
                        <Link :href="route('register')">
                            Create your booking page
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="bg-background/60"
                    >
                        <Link :href="route('login')"
                            >I already have an account</Link
                        >
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
