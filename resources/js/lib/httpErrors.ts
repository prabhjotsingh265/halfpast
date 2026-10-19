import { router } from '@inertiajs/vue3';

/*
 * When a visit gets an error page back instead of a page, Inertia shows it
 * in a pop-up. Instead: an expired form reloads for a fresh token, a page
 * that can't open loads in full so its designed error page shows, and a
 * failed save swaps in the error page the server sent. While developing,
 * the pop-up stays, stack trace and all.
 */
let lastVisit: { method: string; url: URL } | null = null;

export function initializeHttpErrors(): void {
    router.on('start', (event) => {
        lastVisit = event.detail.visit;
    });

    router.on('httpException', (event) => {
        const { status, data } = event.detail.response;

        if (status === 419) {
            event.preventDefault();
            window.location.reload();

            return;
        }

        if (lastVisit?.method === 'get') {
            event.preventDefault();
            window.location.assign(lastVisit.url.href);

            return;
        }

        if (import.meta.env.DEV || typeof data !== 'string') {
            return;
        }

        event.preventDefault();
        document.open();
        document.write(data);
        document.close();
    });
}
