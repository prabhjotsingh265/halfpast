<script setup lang="ts">
import { Plus } from '@lucide/vue';

const questions = [
    {
        q: 'Do my customers need an account?',
        a: 'No. They book with their name and email, and get a link to view or cancel the booking. Nothing to download, no password to forget.',
    },
    {
        q: 'Which payment providers work?',
        a: 'Stripe, PayPal and Razorpay. Your customers only see the ones that work in your currency, so a salon in India can offer UPI through Razorpay, and a clinic in London can offer cards and PayPal.',
    },
    {
        q: 'What happens if two people book the same time?',
        a: 'Exactly one gets it. The database itself refuses a second booking for the same person at the same moment, and the other customer is offered the next free time.',
    },
    {
        q: 'Can I set my own cancellation rules?',
        a: 'Yes. You choose how long before an appointment customers can cancel for a full refund. Cancel later than that, and the deposit is kept.',
    },
    {
        q: 'Does it work with several staff?',
        a: 'Yes. Each person has their own hours, breaks, holidays and services. Customers can pick someone, or choose whoever is free.',
    },
    {
        q: 'Is this a real product?',
        a: "It's a portfolio project, built to work like one. Payments run in test mode, so you can try everything without real money changing hands.",
    },
];
</script>

<template>
    <section id="questions" class="scroll-mt-20 border-t px-5 py-24 md:px-8">
        <div class="mx-auto grid max-w-6xl gap-10 md:grid-cols-[1fr_1.6fr]">
            <div>
                <p
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Questions
                </p>
                <h2 class="mt-3 text-4xl font-[800] font-casual md:text-5xl">
                    The things people ask first.
                </h2>
            </div>

            <div class="divide-y border-y">
                <details
                    v-for="item in questions"
                    :key="item.q"
                    class="group py-5"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-semibold [&::-webkit-details-marker]:hidden"
                    >
                        {{ item.q }}
                        <Plus
                            class="size-5 shrink-0 transition-transform duration-200 group-open:rotate-45"
                        />
                    </summary>
                    <p class="mt-3 max-w-2xl text-muted-foreground">
                        {{ item.a }}
                    </p>
                </details>
            </div>
        </div>
    </section>
</template>
