<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarCog, CreditCard, Database } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    {
        title: 'Booking rules',
        href: route('business.rules.edit'),
        icon: CalendarCog,
    },
    {
        title: 'Payment methods',
        href: route('business.payments.edit'),
        icon: CreditCard,
    },
    {
        title: 'Your data',
        href: route('business.data.edit'),
        icon: Database,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Business settings"
            description="How customers book you, and how they pay."
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-52">
                <nav
                    class="flex flex-col space-y-1"
                    aria-label="Business settings"
                >
                    <Button
                        v-for="item in items"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="size-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1 md:max-w-2xl">
                <slot />
            </div>
        </div>
    </div>
</template>
