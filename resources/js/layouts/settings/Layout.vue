<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: route('profile.edit'),
    },
    {
        title: 'Security',
        href: route('security.edit'),
    },
    {
        title: 'Appearance',
        href: route('appearance.edit'),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();

const isDemo = usePage().props.auth.user?.is_demo === true;
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <p
            v-if="isDemo"
            class="mb-6 max-w-2xl rounded-xl border border-dashed px-4 py-3 text-sm text-muted-foreground"
            data-test="demo-settings-notice"
        >
            This is a shared demo login, so its name, email, password and
            two-factor settings can't be changed. Appearance still can.
        </p>

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
