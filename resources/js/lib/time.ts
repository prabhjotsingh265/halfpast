/**
 * Times inside the app are fractional hours: 15.5 is 3:30 pm. These helpers
 * turn them into the labels people read.
 */

const pad = (n: number) => String(n).padStart(2, '0');

/** 15.5 → "15:30". `step` rounds down to that many minutes. */
export function clock24(hour: number, step = 1): string {
    const minutes = Math.floor(Math.round(hour * 60) / step) * step;

    return `${pad(Math.floor(minutes / 60) % 24)}:${pad(minutes % 60)}`;
}

/** 15.5 → "3:30 pm". */
export function clock12(hour: number): string {
    const minutes = Math.round(hour * 60);
    const h = Math.floor(minutes / 60) % 24;

    return `${h % 12 || 12}:${pad(minutes % 60)} ${h < 12 ? 'am' : 'pm'}`;
}

/** 90 → "1 h 30 min". */
export function duration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

/** Seconds → "9:42". */
export function countdown(seconds: number): string {
    const total = Math.max(0, Math.ceil(seconds));

    return `${Math.floor(total / 60)}:${pad(total % 60)}`;
}

/** The current local time as a fractional hour. */
export function hourNow(date = new Date()): number {
    return date.getHours() + date.getMinutes() / 60;
}
