import { router } from '@inertiajs/vue3';
import type { FlashToast } from '@/types/ui';

/*
 * The toast area only shows toasts made after it appears. It lives in the
 * dashboard layout, which loads separately, so a page that arrives with a
 * toast waits for it rather than losing the toast.
 */
let toasterReady: () => void = () => {};
const ready = new Promise<void>((resolve) => (toasterReady = resolve));

export function markToasterReady(): void {
    toasterReady();
}

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        // Loaded the first time it's needed; most pages never show a toast.
        void Promise.all([import('vue-sonner'), ready]).then(([{ toast }]) =>
            toast[data.type](data.message),
        );
    });
}
