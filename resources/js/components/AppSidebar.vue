<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarOff,
    LayoutGrid,
    SlidersHorizontal,
    Wallet,
    Tags,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutGrid,
    },
    // Setting up what's sold and who works there is for owners.
    ...(page.props.business?.role === 'owner'
        ? [
              { title: 'Services', href: route('services.index'), icon: Tags },
              { title: 'Team', href: route('staff.index'), icon: Users },
              {
                  title: 'Time off',
                  href: route('time-off.index'),
                  icon: CalendarOff,
              },
              {
                  title: 'Earnings',
                  href: route('earnings.index'),
                  icon: Wallet,
              },
              {
                  title: 'Business settings',
                  href: route('business.rules.edit'),
                  icon: SlidersHorizontal,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
