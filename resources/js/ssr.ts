import { createInertiaApp } from '@inertiajs/vue3';
import type { Config } from 'ziggy-js';
import { route, ZiggyVue } from 'ziggy-js';
import { installDirectives, layout, title } from '@/inertia';

/*
 * Server rendering, for the pages anyone can land on: the home page and the
 * booking pages (Laravel decides which; see App\Support\ServerRendering).
 *
 * In the browser, route() and its route list come from the @routes tag. Here
 * the list arrives with each page instead. It's the same for every page, so
 * one render setting it never changes another's links.
 */
globalThis.route = route;

void createInertiaApp({
    title,
    layout,
    withApp: (app, { page }) => {
        globalThis.Ziggy = page.props.ziggy as Config;
        app.use(ZiggyVue);
        installDirectives(app);
    },
});
