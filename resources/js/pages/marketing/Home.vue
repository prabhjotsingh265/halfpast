<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClosingCta from '@/components/marketing/ClosingCta.vue';
import DayScroll from '@/components/marketing/DayScroll.vue';
import FaqSection from '@/components/marketing/FaqSection.vue';
import FeaturesSection from '@/components/marketing/FeaturesSection.vue';
import HeroSection from '@/components/marketing/HeroSection.vue';
import NoShowCalculator from '@/components/marketing/NoShowCalculator.vue';
import PricingSection from '@/components/marketing/PricingSection.vue';
import SiteFooter from '@/components/marketing/SiteFooter.vue';
import SiteHeader from '@/components/marketing/SiteHeader.vue';
import { useSmoothScroll } from '@/composables/useSmoothScroll';

useSmoothScroll();

const links = [
    { label: 'How it works', href: '#how-it-works' },
    { label: 'Features', href: '#features' },
    { label: 'No-shows', href: '#no-shows' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'Questions', href: '#questions' },
];
</script>

<template>
    <Head title="Booking pages that take deposits and never double-book">
        <meta
            name="description"
            content="Halfpast gives salons, clinics and tutors a booking page that takes deposits, sends reminders and never double-books."
        />
    </Head>

    <SiteHeader :links="links" />

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

        <FeaturesSection />

        <section id="no-shows" class="scroll-mt-20 bg-muted px-5 py-24 md:px-8">
            <div class="mx-auto max-w-6xl">
                <p
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    What no-shows cost
                </p>
                <h2
                    class="mt-3 max-w-3xl text-4xl font-[800] font-casual md:text-5xl"
                >
                    Count the empty chairs.
                </h2>
                <p class="mt-4 mb-12 max-w-2xl text-lg text-muted-foreground">
                    Move the sliders to match your week.
                </p>
                <NoShowCalculator />
            </div>
        </section>

        <PricingSection />

        <FaqSection />

        <ClosingCta />
    </main>

    <SiteFooter />
</template>
