import type { App } from 'vue';
import { defineAsyncComponent } from 'vue';

/*
 * What the browser app (app.ts) and the server renderer (ssr.ts) share, so
 * a page renders the same in both and hydrates without a mismatch.
 */

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

export const title = (title: string): string =>
    title ? `${title} - ${appName}` : appName;

export function layout(name: string) {
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
}

export function installDirectives(app: App): void {
    app.directive('focus', {
        mounted: (el: HTMLElement, shouldFocus) => {
            if (shouldFocus.value !== false) {
                el.focus();
            }
        },
    });
}
