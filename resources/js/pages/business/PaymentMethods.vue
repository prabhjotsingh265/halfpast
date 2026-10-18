<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CreditCard, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type GatewayOption = {
    key: string;
    label: string;
    configured: boolean;
    supportsCurrency: boolean;
    enabled: boolean;
    upcoming: boolean;
};

const props = defineProps<{
    currency: string;
    gateways: GatewayOption[];
    depositServices: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Business settings', href: route('business.rules.edit') },
        ],
    },
});

const form = useForm({
    gateways: props.gateways.filter((g) => g.enabled).map((g) => g.key),
});

const errors = computed(() => form.errors as Record<string, string>);

function available(gateway: GatewayOption): boolean {
    return gateway.configured && gateway.supportsCurrency;
}

function toggle(gateway: GatewayOption): void {
    if (!available(gateway)) {
        return;
    }

    form.gateways = form.gateways.includes(gateway.key)
        ? form.gateways.filter((key) => key !== gateway.key)
        : [...form.gateways, gateway.key];
}

function why(gateway: GatewayOption): string | null {
    if (gateway.upcoming) {
        return 'Coming soon.';
    }

    if (!gateway.configured) {
        return 'Its keys haven’t been added yet.';
    }

    return gateway.supportsCurrency
        ? null
        : `Doesn’t take ${props.currency} payments.`;
}

function submit(): void {
    form.put(route('business.payments.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Payment methods" />

    <form class="space-y-8" @submit.prevent="submit">
        <Heading
            variant="small"
            title="Payment methods"
            description="How customers pay deposits. Money is only taken once the booking is definitely theirs."
        />

        <div
            v-if="depositServices > 0 && form.gateways.length === 0"
            class="flex gap-3 rounded-xl border border-sun-deep/30 bg-sun/10 p-4 text-sm"
            role="alert"
            data-test="no-methods-warning"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-sun-deep" />
            <p>
                {{ depositServices }}
                {{ depositServices === 1 ? 'service takes' : 'services take' }}
                a deposit, so customers can’t book
                {{ depositServices === 1 ? 'it' : 'them' }} online until a
                payment method is switched on.
            </p>
        </div>

        <ul class="space-y-3">
            <li v-for="gateway in gateways" :key="gateway.key">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="form.gateways.includes(gateway.key)"
                    :disabled="!available(gateway)"
                    class="flex w-full items-center gap-4 rounded-xl border bg-card p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        form.gateways.includes(gateway.key)
                            ? 'border-sun-deep/50 bg-sun/5'
                            : 'hover:bg-accent'
                    "
                    :data-test="`gateway-${gateway.key}`"
                    @click="toggle(gateway)"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-lg bg-muted"
                    >
                        <CreditCard class="size-5 text-muted-foreground" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{
                            gateway.label
                        }}</span>
                        <span class="block text-sm text-muted-foreground">
                            {{
                                why(gateway) ??
                                (gateway.key === 'test'
                                    ? 'Pretend payments for trying things out. Never available on the live site.'
                                    : `Takes ${currency} payments.`)
                            }}
                        </span>
                    </span>
                    <span
                        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                        :class="
                            form.gateways.includes(gateway.key)
                                ? 'bg-sun-deep'
                                : 'bg-muted-foreground/30'
                        "
                        aria-hidden="true"
                    >
                        <span
                            class="size-4 rounded-full bg-background shadow-sm transition-transform duration-200"
                            :class="
                                form.gateways.includes(gateway.key)
                                    ? 'translate-x-4.5'
                                    : 'translate-x-0.5'
                            "
                        />
                    </span>
                </button>
            </li>
        </ul>
        <InputError
            :message="
                errors.gateways ??
                Object.entries(errors).find(([key]) =>
                    key.startsWith('gateways.'),
                )?.[1]
            "
        />

        <Button
            type="submit"
            :disabled="form.processing || !form.isDirty"
            data-test="save-payments"
        >
            <Spinner v-if="form.processing" />
            Save payment methods
        </Button>
    </form>
</template>
