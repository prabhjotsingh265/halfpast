<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HeroSection from '@/components/marketing/HeroSection.vue';
import SiteFooter from '@/components/marketing/SiteFooter.vue';
import SiteHeader from '@/components/marketing/SiteHeader.vue';
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
    </main>

    <SiteFooter />
</template>
