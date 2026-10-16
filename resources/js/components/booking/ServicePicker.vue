<script setup lang="ts">
import { Check, Clock } from '@lucide/vue';
import { formatMoney } from '@/lib/money';
import { duration } from '@/lib/time';
import type { BookableService } from '@/types/booking';

defineProps<{
    services: BookableService[];
    selected: number | null;
    currency: string;
}>();

const emit = defineEmits<{ select: [id: number] }>();
</script>

<template>
    <div
        class="grid gap-2.5 sm:grid-cols-2"
        role="radiogroup"
        aria-label="Service"
    >
        <button
            v-for="(service, index) in services"
            :key="service.id"
            type="button"
            role="radio"
            :aria-checked="selected === service.id"
            class="group relative flex animate-in flex-col rounded-2xl border bg-card p-4 text-left transition-[border-color,box-shadow,transform] duration-200 fade-in slide-in-from-bottom-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-[0.99]"
            :class="
                selected === service.id
                    ? 'border-foreground shadow-[inset_0_0_0_1px_var(--ink)]'
                    : 'hover:border-foreground/30 hover:shadow-sm'
            "
            :style="{ animationDelay: `${index * 40}ms` }"
            :data-test="`service-${service.id}`"
            @click="emit('select', service.id)"
        >
            <span class="flex items-start justify-between gap-3">
                <span class="font-semibold">{{ service.name }}</span>
                <span class="font-semibold font-time">{{
                    formatMoney(service.price, currency)
                }}</span>
            </span>
            <span
                v-if="service.description"
                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                >{{ service.description }}</span
            >
            <span
                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-1">
                    <Clock class="size-3.5" />
                    {{ duration(service.duration_minutes) }}
                </span>
                <span>
                    {{
                        service.deposit
                            ? `${formatMoney(service.deposit, currency)} deposit to book`
                            : 'Nothing to pay now'
                    }}
                </span>
            </span>
            <span
                v-if="selected === service.id"
                class="absolute -top-2 -right-2 grid size-6 animate-in place-items-center rounded-full bg-foreground text-background zoom-in-50"
                aria-hidden="true"
            >
                <Check class="size-3.5" />
            </span>
        </button>
    </div>
</template>
