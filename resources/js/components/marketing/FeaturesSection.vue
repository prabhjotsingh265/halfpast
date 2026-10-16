<script setup lang="ts">
import DoubleBookDemo from '@/components/marketing/DoubleBookDemo.vue';
import FeatureCard from '@/components/marketing/FeatureCard.vue';
import HoldRingDemo from '@/components/marketing/HoldRingDemo.vue';
import RemindersDemo from '@/components/marketing/RemindersDemo.vue';
import TimeZonesDemo from '@/components/marketing/TimeZonesDemo.vue';
</script>

<template>
    <section id="features" class="scroll-mt-20 px-5 py-24 md:px-8">
        <div class="mx-auto max-w-6xl">
            <p
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                What it does
            </p>
            <h2
                class="mt-3 max-w-3xl text-4xl font-[800] font-casual md:text-5xl"
            >
                Try it here. It's how the real thing behaves.
            </h2>
            <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                Every demo below runs the same rules your booking page will.
            </p>

            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <FeatureCard
                    class="lg:col-span-2"
                    eyebrow="Never double-booked"
                    title="Two customers, one time, one winner."
                    text="When two people tap the same time together, exactly one gets it. The other is offered the next free time straight away."
                >
                    <DoubleBookDemo />
                </FeatureCard>

                <FeatureCard
                    class="lg:col-span-2"
                    eyebrow="Held while they pay"
                    title="Ten minutes to pay, and nobody can take it."
                    text="Picking a time holds it while the customer pays the deposit. If they wander off, it frees itself again."
                >
                    <HoldRingDemo />
                </FeatureCard>

                <FeatureCard
                    eyebrow="Reminders"
                    title="Sent once, right on time."
                    text="A confirmation straight away, then a reminder the day before and two hours before."
                >
                    <RemindersDemo />
                </FeatureCard>

                <FeatureCard
                    eyebrow="Time zones"
                    title="Their time, not yours."
                    text="Customers booking from abroad see times in their own clock, so nobody turns up an hour late."
                >
                    <TimeZonesDemo />
                </FeatureCard>
            </div>
        </div>
    </section>
</template>
