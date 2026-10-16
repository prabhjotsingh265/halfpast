<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const included = [
    'Your own booking page, with as many services and staff as you need',
    'Deposits through Stripe, PayPal or Razorpay',
    'Reminders a day before and two hours before',
    'A live calendar for every staff member',
    'No double bookings, ever',
];

const later = [
    'Your own domain',
    'WhatsApp and SMS reminders',
    'Two-way Google Calendar sync',
];
</script>

<template>
    <section id="pricing" class="scroll-mt-20 px-5 py-24 md:px-8">
        <div class="mx-auto max-w-6xl">
            <p
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Pricing
            </p>
            <h2
                class="mt-3 max-w-3xl text-4xl font-[800] font-casual md:text-5xl"
            >
                Free while it's in beta.
            </h2>
            <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
                No Halfpast fees on bookings. Your payment provider charges its
                usual card fees.
            </p>

            <div class="mt-12 grid gap-6 md:grid-cols-[1.3fr_1fr]">
                <div
                    class="rounded-2xl border-2 border-foreground bg-card p-6 md:p-8"
                >
                    <div class="flex items-baseline justify-between gap-4">
                        <h3 class="text-xl font-semibold">Beta</h3>
                        <p class="text-4xl font-[650] font-time">Free</p>
                    </div>
                    <ul class="mt-6 space-y-3">
                        <li
                            v-for="item in included"
                            :key="item"
                            class="flex gap-3"
                        >
                            <Check
                                class="mt-0.5 size-5 shrink-0 text-success"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <Button as-child size="lg" class="mt-8 w-full">
                        <Link :href="route('register')"
                            >Create your booking page</Link
                        >
                    </Button>
                </div>

                <div class="rounded-2xl border bg-muted p-6 md:p-8">
                    <h3 class="text-xl font-semibold">Coming later</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        On the list for after the beta.
                    </p>
                    <ul class="mt-6 space-y-3 text-muted-foreground">
                        <li
                            v-for="item in later"
                            :key="item"
                            class="flex gap-3"
                        >
                            <span
                                class="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>
