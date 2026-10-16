import type { Directive } from 'vue';
import type { route as ziggyRoute } from 'ziggy-js';
import type { Auth } from '@/types/auth';
import type { CurrentBusiness } from '@/types/business';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

// `route()` comes from the @routes Blade directive in the browser, and from
// the ZiggyVue plugin inside templates.
declare global {
    const route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            business: CurrentBusiness | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
        route: typeof ziggyRoute;
    }
}
