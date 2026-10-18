<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleAlert, Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import MoneyChart from '@/components/earnings/MoneyChart.vue';
import Heading from '@/components/Heading.vue';
import { formatMoney } from '@/lib/money';

type Movement = {
    id: number;
    at: string;
    type: 'deposit' | 'refund';
    amount: number;
    gateway: string;
    service: string | null;
    customer: string | null;
    reference: string | null;
};

const props = defineProps<{
    currency: string;
    period: string;
    periods: { key: string; label: string }[];
    totals: { taken: number; refunded: number; balance: number };
    refunds: { pending: number; pendingCount: number; failedCount: number };
    days: { date: string; taken: number; refunded: number }[];
    byGateway: {
        gateway: string;
        label: string;
        taken: number;
        refunded: number;
    }[];
    recent: Movement[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Earnings', href: route('earnings.index') }],
    },
});

const loading = ref(false);
const money = (minor: number) => formatMoney(minor, props.currency);

function choose(period: string): void {
    router.get(
        route('earnings.index'),
        { period },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

const when = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
});

const periodLabel = computed(
    () => props.periods.find((p) => p.key === props.period)?.label ?? '',
);
</script>

<template>
    <Head title="Earnings" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <Heading
            title="Earnings"
            description="Deposits customers have paid, and what's gone back to them."
        />

        <div
            class="flex flex-wrap gap-1.5"
            role="radiogroup"
            aria-label="Period"
            data-test="periods"
        >
            <button
                v-for="option in periods"
                :key="option.key"
                type="button"
                role="radio"
                :aria-checked="option.key === period"
                class="rounded-full border px-3 py-1 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    option.key === period
                        ? 'border-foreground bg-foreground text-background'
                        : 'hover:bg-accent'
                "
                @click="choose(option.key)"
            >
                {{ option.label }}
            </button>
        </div>

        <div
            class="space-y-6 transition-opacity duration-200"
            :class="loading && 'opacity-50'"
        >
            <section
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                aria-label="Totals"
            >
                <div
                    class="rounded-xl border bg-card p-4 sm:col-span-2 lg:col-span-1"
                >
                    <p class="text-sm text-muted-foreground">Balance</p>
                    <p
                        class="mt-1 text-3xl font-semibold tracking-tight md:text-4xl"
                        data-test="balance"
                    >
                        {{ money(totals.balance) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        All deposits taken, less refunds
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Deposits taken</p>
                    <p class="mt-1 text-2xl font-semibold" data-test="taken">
                        {{ money(totals.taken) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ periodLabel }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Refunded</p>
                    <p class="mt-1 text-2xl font-semibold" data-test="refunded">
                        {{ money(totals.refunded) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ periodLabel }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Refunds on the way
                    </p>
                    <p class="mt-1 text-2xl font-semibold" data-test="pending">
                        {{ money(refunds.pending) }}
                    </p>
                    <p
                        v-if="refunds.failedCount > 0"
                        class="mt-1 flex items-center gap-1 text-xs text-destructive"
                        data-test="failed-refunds"
                    >
                        <CircleAlert class="size-3.5" />
                        {{ refunds.failedCount }}
                        {{
                            refunds.failedCount === 1
                                ? 'refund needs'
                                : 'refunds need'
                        }}
                        looking at
                    </p>
                    <p
                        v-else-if="refunds.pendingCount > 0"
                        class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Clock class="size-3.5" />
                        {{ refunds.pendingCount }} being retried automatically
                    </p>
                    <p v-else class="mt-1 text-xs text-muted-foreground">
                        Nothing waiting
                    </p>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-4 md:p-5">
                <h2 class="mb-3 font-semibold">Money in and out, by day</h2>
                <MoneyChart :days="days" :currency="currency" />
            </section>

            <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <section class="rounded-xl border bg-card p-4 md:p-5">
                    <h2 class="mb-3 font-semibold">By payment method</h2>
                    <ul v-if="byGateway.length" class="space-y-3">
                        <li
                            v-for="row in byGateway"
                            :key="row.gateway"
                            class="text-sm"
                        >
                            <p class="font-medium">{{ row.label }}</p>
                            <p
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Taken</span>
                                <span class="tabular-nums">{{
                                    money(row.taken)
                                }}</span>
                            </p>
                            <p
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Refunded</span>
                                <span class="tabular-nums">{{
                                    money(row.refunded)
                                }}</span>
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No payments in this period.
                    </p>
                </section>

                <section class="rounded-xl border bg-card p-4 md:p-5">
                    <h2 class="mb-3 font-semibold">Latest</h2>
                    <ul
                        v-if="recent.length"
                        class="divide-y"
                        data-test="recent"
                    >
                        <li
                            v-for="movement in recent"
                            :key="movement.id"
                            class="flex items-center gap-3 py-2.5 text-sm"
                        >
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :class="
                                    movement.type === 'deposit'
                                        ? 'bg-money-in'
                                        : 'bg-money-out'
                                "
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate">
                                    {{
                                        movement.type === 'deposit'
                                            ? 'Deposit'
                                            : 'Refund'
                                    }}
                                    <template v-if="movement.service">
                                        · {{ movement.service }}</template
                                    >
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ movement.customer ?? 'Customer' }} ·
                                    {{ movement.gateway }} ·
                                    {{ when.format(new Date(movement.at)) }}
                                </p>
                            </div>
                            <p class="shrink-0 font-medium tabular-nums">
                                {{ movement.type === 'refund' ? '−' : '+'
                                }}{{ money(Math.abs(movement.amount)) }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No money in or out in this period yet.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
