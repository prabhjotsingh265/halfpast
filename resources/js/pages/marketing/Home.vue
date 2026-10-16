<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DayScroll from '@/components/marketing/DayScroll.vue';
import HeroSection from '@/components/marketing/HeroSection.vue';
import SiteFooter from '@/components/marketing/SiteFooter.vue';
import SiteHeader from '@/components/marketing/SiteHeader.vue';
import { useSmoothScroll } from '@/composables/useSmoothScroll';

useSmoothScroll();
</script>

<template>
    <Head title="Booking pages that take deposits and never double-book">
        <meta
            name="description"
            content="Halfpast gives salons, clinics and tutors a booking page that takes deposits, sends reminders and never double-books."
        />
    </Head>

    <SiteHeader />

    <main>
        <HeroSection />

        <section
            id="how-it-works"
            class="scroll-mt-20 px-5 pt-20 pb-10 md:px-8"
        >
            <div class="mx-auto max-w-6xl">
                <p
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    How a day goes
                </p>
                <h2
                    class="mt-3 max-w-3xl text-4xl font-[800] font-casual md:text-5xl"
                >
                    Your whole day, coloured by the hour it happens.
                </h2>
                <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                    Scroll through a Tuesday at Glow Studio. Bookings arrive
                    while the team works, and two customers go for the same
                    time.
                </p>
            </div>
        </section>

        <DayScroll />
    </main>

    <SiteFooter />
</template>
