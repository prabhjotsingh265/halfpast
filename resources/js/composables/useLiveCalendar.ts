import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { live } from '@/lib/live';

/**
 * While the page is open, reloads some of its props when the business's
 * calendar changes: a booking or time off added, moved or changed. A beat
 * passes first, so a burst of changes reloads once.
 */
export function useLiveCalendar(options: {
    /** The props to fetch again. */
    only: string[];
    /** Whether a change to these local dates matters to this page. */
    affects?: (from: string, to: string) => boolean;
}): void {
    const page = usePage();
    let channel: string | null = null;
    let timer = 0;

    onMounted(() => {
        const echo = live();
        const businessId = page.props.business?.id;

        if (!echo || !businessId) {
            return;
        }

        channel = `business.${businessId}.calendar`;
        echo.private(channel).listen(
            '.calendar.changed',
            (change: { from: string; to: string }) => {
                if (
                    options.affects &&
                    !options.affects(change.from, change.to)
                ) {
                    return;
                }

                window.clearTimeout(timer);
                timer = window.setTimeout(
                    () => router.reload({ only: options.only }),
                    300,
                );
            },
        );
    });

    onBeforeUnmount(() => {
        window.clearTimeout(timer);

        if (channel) {
            live()?.leave(channel);
        }
    });
}
