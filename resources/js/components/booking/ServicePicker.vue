<script setup lang="ts">
import { Check } from '@lucide/vue';
import DurationDial from '@/components/booking/DurationDial.vue';
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
            class="group relative flex animate-in gap-3.5 rounded-2xl border bg-card p-4 text-left transition-[border-color,box-shadow,background-color,transform] duration-200 fade-in slide-in-from-bottom-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-[0.99]"
            :class="
                selected === service.id
                    ? 'border-sun bg-sun/[0.07] shadow-[inset_0_0_0_1px_var(--sun)]'
                    : 'hover:border-foreground/30 hover:shadow-sm'
            "
            :style="{ animationDelay: `${index * 40}ms` }"
            :data-test="`service-${service.id}`"
            @click="emit('select', service.id)"
        >
            <DurationDial
                :minutes="service.duration_minutes"
                :active="selected === service.id"
            />
            <span class="flex min-w-0 flex-1 flex-col">
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
                    class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                >
                    <span class="font-medium text-foreground/80 font-time">{{
                        duration(service.duration_minutes)
                    }}</span>
                    <span aria-hidden="true">·</span>
                    <span>
                        {{
                            service.deposit
                                ? `${formatMoney(service.deposit, currency)} deposit to book`
                                : 'Nothing to pay now'
                        }}
                    </span>
                </span>
            </span>
            <span
                v-if="selected === service.id"
                class="absolute -top-2 -right-2 grid size-6 animate-in place-items-center rounded-full bg-sun text-[#15181F] zoom-in-50"
                aria-hidden="true"
            >
                <Check class="size-3.5" />
            </span>
        </button>
    </div>
</template>
