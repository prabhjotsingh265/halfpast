/**
 * Every time slot is tinted by the hour it starts at: pale rose at opening,
 * butter yellow mid-morning, clear sky at noon, apricot at dusk, violet in
 * the evening. The colours between the six fixed points are blended in OKLCH,
 * so lightness and hue move evenly instead of muddying the way RGB blends do.
 */

type Oklch = readonly [lightness: number, chroma: number, hue: number];
type Stop = readonly [hour: number, colour: Oklch];

const LIGHT: readonly Stop[] = [
    [6, [0.93, 0.032, 20]],
    [9, [0.95, 0.05, 90]],
    [12, [0.95, 0.035, 210]],
    [15, [0.93, 0.033, 240]],
    [18, [0.91, 0.052, 55]],
    [21, [0.88, 0.045, 290]],
];

const DARK: readonly Stop[] = LIGHT.map(
    ([hour, [, , hue]]) => [hour, [0.29, 0.045, hue]] as const,
);

/** Interpolate a hue along the shorter way round the colour wheel. */
function mixHue(from: number, to: number, t: number): number {
    const delta = ((to - from + 540) % 360) - 180;

    return (from + delta * t + 360) % 360;
}

export function daylightOklch(
    hour: number,
    mode: 'light' | 'dark' = 'light',
): Oklch {
    const stops = mode === 'dark' ? DARK : LIGHT;
    const first = stops[0];
    const last = stops[stops.length - 1];

    if (hour <= first[0]) {
        return first[1];
    }

    if (hour >= last[0]) {
        return last[1];
    }

    const nextIndex = stops.findIndex(([at]) => at > hour);
    const [fromHour, from] = stops[nextIndex - 1];
    const [toHour, to] = stops[nextIndex];
    const t = (hour - fromHour) / (toHour - fromHour);

    return [
        from[0] + (to[0] - from[0]) * t,
        from[1] + (to[1] - from[1]) * t,
        mixHue(from[2], to[2], t),
    ];
}

/** CSS colour for a slot starting at `hour` (fractional, so 15.5 is 3:30 pm). */
export function daylight(
    hour: number,
    mode: 'light' | 'dark' = 'light',
): string {
    const [l, c, h] = daylightOklch(hour, mode);

    return `oklch(${l.toFixed(3)} ${c.toFixed(3)} ${h.toFixed(1)})`;
}

/**
 * CSS custom properties for a block running from one hour to another, in both
 * modes, so a gradient can follow the theme without knowing it:
 * bg-[linear-gradient(90deg,var(--from),var(--to))]
 * dark:bg-[linear-gradient(90deg,var(--from-dark),var(--to-dark))]
 */
export function daylightRange(
    fromHour: number,
    toHour: number,
): Record<string, string> {
    return {
        '--from': daylight(fromHour),
        '--to': daylight(toHour),
        '--from-dark': daylight(fromHour, 'dark'),
        '--to-dark': daylight(toHour, 'dark'),
    };
}
