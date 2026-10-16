<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { duration } from '@/lib/time';
import type { Service } from '@/types/service';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Services', href: route('services.index') }],
    },
});

defineProps<{
    services: Service[];
    currency: string;
}>();

function remove(service: Service): void {
    if (
        window.confirm(
            `Delete “${service.name}”? Customers won't be able to book it any more.`,
        )
    ) {
        router.delete(route('services.destroy', service.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Services" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Services"
                description="What customers can book, how long it takes and what it costs."
            />
            <Button as-child>
                <Link :href="route('services.create')">
                    <Plus class="size-4" />
                    Add a service
                </Link>
            </Button>
        </div>

        <div
            v-if="services.length === 0"
            class="flex flex-col items-center rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <span class="grid size-12 place-items-center rounded-full bg-muted">
                <Tags class="size-5 text-muted-foreground" />
            </span>
            <h2 class="mt-4 text-lg font-semibold">No services yet</h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Add the first thing customers can book, like a haircut or a
                consultation.
            </p>
            <Button as-child class="mt-6">
                <Link :href="route('services.create')"
                    >Add your first service</Link
                >
            </Button>
        </div>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="service in services"
                :key="service.id"
                class="flex flex-wrap items-center gap-4 px-4 py-4 md:px-5"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold">
                        {{ service.name }}
                        <Badge v-if="!service.is_active" variant="secondary"
                            >Hidden</Badge
                        >
                        <Badge
                            v-else-if="service.staff_count === 0"
                            variant="outline"
                            class="border-destructive/40 text-destructive"
                            >No one offers this</Badge
                        >
                    </p>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="flex items-center gap-1">
                            <Clock class="size-3.5" />
                            {{ duration(service.duration_minutes) }}
                            <template v-if="service.buffer_after_minutes">
                                + {{ service.buffer_after_minutes }} min after
                            </template>
                        </span>
                        <span>
                            {{
                                service.deposit
                                    ? `${formatMoney(service.deposit, currency)} deposit`
                                    : 'No deposit'
                            }}
                        </span>
                    </p>
                </div>
                <p class="font-semibold font-time">
                    {{ formatMoney(service.price, currency) }}
                </p>
                <div class="flex gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon"
                        :aria-label="`Edit ${service.name}`"
                    >
                        <Link :href="route('services.edit', service.id)">
                            <Pencil class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="`Delete ${service.name}`"
                        @click="remove(service)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
