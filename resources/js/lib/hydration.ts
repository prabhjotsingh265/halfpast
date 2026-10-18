import { ref } from 'vue';
import { localZone } from '@/lib/zones';

/**
 * Whether the page is running in the browser yet.
 *
 * The server can't know the visitor's language or time zone. So a page it
 * renders, and the browser's first render of that page, show dates in a
 * fixed locale and in the business's own zone, and match exactly. Once the
 * page is running it switches to the visitor's. Pages the browser renders
 * from the start use the visitor's straight away.
 */
export const hydrated = ref(
    typeof document !== 'undefined' &&
        !document.querySelector('[data-server-rendered]'),
);

export function markHydrated(): void {
    hydrated.value = true;
}

/** The locale to format dates in: the visitor's, or a fixed one until hydrated. */
export function displayLocale(): string | undefined {
    return hydrated.value ? undefined : 'en-US';
}

/** The visitor's time zone, or the business's until hydrated. */
export function visitorZone(businessZone: string): string {
    return hydrated.value ? localZone() : businessZone;
}
