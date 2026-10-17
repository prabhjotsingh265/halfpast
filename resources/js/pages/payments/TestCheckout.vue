<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import { formatMoney } from '@/lib/money';

/**
 * Stands in for a real gateway's payment page. Deliberately plain and
 * grey, so it never looks like part of Halfpast.
 */
defineProps<{
    order: string;
    amount: number;
    currency: string;
    state: string;
    cancelUrl: string;
}>();

const form = useForm({ action: '' });

function send(action: string): void {
    form.action = action;
    form.post(window.location.pathname + window.location.search);
}
</script>

<template>
    <Head title="Test payment" />

    <div
        class="grid min-h-svh place-items-center bg-[#eef0f3] px-4 py-10 font-sans text-[#1f2328]"
    >
        <div
            class="w-full max-w-sm rounded-lg border border-[#d0d7de] bg-white p-6 shadow-sm"
        >
            <p
                class="inline-flex items-center gap-1.5 rounded bg-[#fff8c5] px-2 py-0.5 text-xs font-semibold text-[#7d4e00]"
            >
                <FlaskConical class="size-3.5" />
                TEST MODE: no real money moves
            </p>
            <h1 class="mt-4 text-sm text-[#59636e]">Pay deposit</h1>
            <p class="text-3xl font-semibold" data-test="test-amount">
                {{ formatMoney(amount, currency) }}
            </p>

            <p
                v-if="state !== 'pending'"
                class="mt-4 rounded bg-[#f6f8fa] p-3 text-sm"
            >
                This payment is already {{ state }}.
            </p>

            <div v-else class="mt-6 grid gap-2">
                <button
                    type="button"
                    class="rounded-md bg-[#1f883d] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a7f37] disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-pay"
                    @click="send('pay')"
                >
                    Pay
                </button>
                <button
                    type="button"
                    class="rounded-md border border-[#d0d7de] px-4 py-2 text-sm hover:bg-[#f6f8fa] disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-pay-capture"
                    @click="send('pay_and_capture')"
                >
                    Pay, taking the money straight away
                </button>
                <button
                    type="button"
                    class="rounded-md border border-[#d0d7de] px-4 py-2 text-sm text-[#cf222e] hover:bg-[#fff5f5] disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-decline"
                    @click="send('decline')"
                >
                    Decline the card
                </button>
                <p
                    class="mt-3 border-t border-[#d0d7de] pt-3 text-xs font-semibold text-[#59636e]"
                >
                    Try the awkward cases
                </p>
                <button
                    type="button"
                    class="rounded-md border border-[#d0d7de] px-4 py-2 text-sm hover:bg-[#f6f8fa] disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-pay-late"
                    @click="send('pay_late')"
                >
                    Pay after the hold has run out
                </button>
                <button
                    type="button"
                    class="rounded-md border border-[#d0d7de] px-4 py-2 text-sm hover:bg-[#f6f8fa] disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-pay-taken"
                    @click="send('pay_after_taken')"
                >
                    Pay after someone else took the time
                </button>
                <button
                    type="button"
                    class="px-4 py-2 text-sm text-[#59636e] hover:underline disabled:opacity-60"
                    :disabled="form.processing"
                    data-test="test-cancel"
                    @click="send('cancel')"
                >
                    Cancel and go back
                </button>
            </div>
        </div>
    </div>
</template>
