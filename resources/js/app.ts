import '@fontsource-variable/recursive/full.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from '@/composables/useAppearance';
import { installDirectives, layout, title } from '@/inertia';
import { initializeFlashToast } from '@/lib/flashToast';
import { markHydrated } from '@/lib/hydration';

void createInertiaApp({
    title,
    layout,
    withApp: (app) => {
        app.use(ZiggyVue);
        installDirectives(app);
    },
    progress: {
        color: '#A24E10',
    },
}).then(markHydrated);

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
