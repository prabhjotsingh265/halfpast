<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/money';
import type { CheckoutBooking, CheckoutCustomer } from '@/types/booking';

/**
 * Who the booking is for. With nothing to pay this confirms it; with a
 * deposit it moves on to paying.
 */
const props = defineProps<{
    booking: CheckoutBooking;
    customer: CheckoutCustomer | null;
}>();

const business = computed(() => usePage().props.business!);

const form = useForm({
    name: props.customer?.name ?? '',
    email: props.customer?.email ?? '',
    phone: props.customer?.phone ?? '',
    notes: props.customer?.notes ?? '',
});

const errors = computed(() => form.errors as Record<string, string>);

function submit(): void {
    form.post(
        route('booking.details', {
            business: business.value.slug,
            booking: props.booking.code,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <form
        class="animate-in space-y-5 rounded-2xl border bg-card p-5 fade-in slide-in-from-bottom-2 md:p-6"
        data-test="details-form"
        @submit.prevent="submit"
    >
        <div>
            <h2 class="text-lg font-semibold">Your details</h2>
            <p class="text-sm text-muted-foreground">
                {{ business.name }} uses these to confirm and remind you.
                Nothing else.
            </p>
        </div>

        <InputError :message="errors.booking" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    autocomplete="name"
                    required
                    maxlength="120"
                    v-focus
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    inputmode="email"
                    required
                    maxlength="255"
                />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="phone"
                    >Phone
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    ></Label
                >
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    autocomplete="tel"
                    inputmode="tel"
                    maxlength="30"
                />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label for="notes"
                    >Anything they should know?
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    ></Label
                >
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    maxlength="500"
                    placeholder="Allergies, access needs, the look you're after…"
                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="form.errors.notes" />
            </div>
        </div>

        <Button
            type="submit"
            size="lg"
            class="w-full rounded-xl"
            :disabled="form.processing"
            data-test="confirm"
        >
            <Spinner v-if="form.processing" />
            {{
                booking.deposit
                    ? `Continue to pay ${formatMoney(booking.deposit, booking.currency)}`
                    : 'Confirm booking'
            }}
        </Button>
        <p
            class="flex items-center justify-center gap-1.5 text-xs text-muted-foreground"
        >
            <Lock class="size-3" />
            {{
                booking.deposit
                    ? 'You pay on the payment provider’s own secure page.'
                    : 'Nothing to pay now.'
            }}
        </p>
    </form>
</template>
