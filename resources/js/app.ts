import '@fontsource-variable/recursive/full.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeFlashToast } from '@/lib/flashToast';

// Layouts load with the pages that use them, so a customer's booking page
// doesn't download the dashboard's sidebar, menus and the rest.
const AppLayout = defineAsyncComponent(() => import('@/layouts/AppLayout.vue'));
const AuthLayout = defineAsyncComponent(
    () => import('@/layouts/AuthLayout.vue'),
);
const BusinessLayout = defineAsyncComponent(
    () => import('@/layouts/business/Layout.vue'),
);
const SettingsLayout = defineAsyncComponent(
    () => import('@/layouts/settings/Layout.vue'),
);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('marketing/'):
            case name.startsWith('booking/'):
            case name.startsWith('payments/'):
                return null;
            case name.startsWith('auth/'):
            case name.startsWith('onboarding/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('business/'):
                return [AppLayout, BusinessLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.use(ZiggyVue);

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#A24E10',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
