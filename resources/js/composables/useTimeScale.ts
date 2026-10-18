import type { MaybeRefOrGetter } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';

/** Pixels per minute on a calendar grid: an hour is 90px tall. */
export const PPM = 1.5;

/**
 * The vertical scale of a calendar grid: from an hour before the earliest
 * start to an hour after the latest finish, at least eight hours tall.
 */
export function useTimeScale(
    bounds: MaybeRefOrGetter<{ starts: number[]; ends: number[] }>,
) {
    const range = computed<[number, number]>(() => {
        const { starts, ends } = toValue(bounds);

        if (starts.length === 0) {
            return [9 * 60, 18 * 60];
        }

        const from = Math.max(
            0,
            Math.floor(Math.min(...starts) / 60) * 60 - 60,
        );
        const to = Math.min(
            24 * 60,
            Math.ceil(Math.max(...ends) / 60) * 60 + 60,
        );

        return [from, Math.min(24 * 60, Math.max(to, from + 8 * 60))];
    });

    const hourMarks = computed(() => {
        const marks: number[] = [];

        for (let m = range.value[0]; m <= range.value[1]; m += 60) {
            marks.push(m);
        }

        return marks;
    });

    const height = computed(() => (range.value[1] - range.value[0]) * PPM);
    const top = (minutes: number) => (minutes - range.value[0]) * PPM;
    const inRange = (minutes: number | null): minutes is number =>
        minutes !== null &&
        minutes >= range.value[0] &&
        minutes <= range.value[1];

    return { range, hourMarks, height, top, inRange };
}

/** The current moment, refreshed every 30 seconds while mounted. */
export function useNow() {
    const now = ref(new Date());
    let timer = 0;

    onMounted(() => {
        timer = window.setInterval(() => (now.value = new Date()), 30_000);
    });
    onBeforeUnmount(() => window.clearInterval(timer));

    return now;
}
