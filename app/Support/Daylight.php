<?php

namespace App\Support;

/**
 * The daylight ramp from resources/js/lib/daylight.ts, for what the server
 * draws itself: error pages, share images and emails. Pale rose at opening,
 * butter yellow mid-morning, clear sky at noon, apricot at dusk, violet in
 * the evening, blended in OKLCH between six fixed points.
 */
class Daylight
{
    /** @var list<array{float, array{float, float, float}}> */
    private const LIGHT = [
        [6.0, [0.93, 0.032, 20.0]],
        [9.0, [0.95, 0.05, 90.0]],
        [12.0, [0.95, 0.035, 210.0]],
        [15.0, [0.93, 0.033, 240.0]],
        [18.0, [0.91, 0.052, 55.0]],
        [21.0, [0.88, 0.045, 290.0]],
    ];

    /**
     * The colour for an hour of the day (fractional: 15.5 is 3:30 pm).
     *
     * @return array{float, float, float} OKLCH lightness, chroma and hue
     */
    public static function oklch(float $hour, bool $dark = false): array
    {
        $stops = array_map(
            fn (array $stop) => $dark ? [$stop[0], [0.29, 0.045, $stop[1][2]]] : $stop,
            self::LIGHT,
        );
        $first = $stops[0];
        $last = $stops[count($stops) - 1];

        if ($hour <= $first[0]) {
            return $first[1];
        }

        if ($hour >= $last[0]) {
            return $last[1];
        }

        $next = 1;
        while ($stops[$next][0] <= $hour) {
            $next++;
        }

        [$fromHour, $from] = $stops[$next - 1];
        [$toHour, $to] = $stops[$next];
        $span = $toHour - $fromHour;
        $t = $span > 0 ? ($hour - $fromHour) / $span : 0.0;

        return [
            $from[0] + ($to[0] - $from[0]) * $t,
            $from[1] + ($to[1] - $from[1]) * $t,
            self::mixHue($from[2], $to[2], $t),
        ];
    }

    /**
     * A colour for every hour of a 24-hour dial. Overnight, evening violet
     * fades back to dawn rose, so the dial has no seam at midnight.
     *
     * @return array{float, float, float}
     */
    public static function aroundTheClock(float $hour, bool $dark = false): array
    {
        $hour = fmod(fmod($hour, 24) + 24, 24);

        if ($hour > 6 && $hour < 21) {
            return self::oklch($hour, $dark);
        }

        $evening = self::oklch(21, $dark);
        $dawn = self::oklch(6, $dark);
        $t = (($hour < 21 ? $hour + 24 : $hour) - 21) / 9;

        return [
            $evening[0] + ($dawn[0] - $evening[0]) * $t,
            $evening[1] + ($dawn[1] - $evening[1]) * $t,
            self::mixHue($evening[2], $dawn[2], $t),
        ];
    }

    /** A CSS conic gradient running the dial round from midnight at the top. */
    public static function dialGradient(bool $dark = false): string
    {
        $stops = [];

        for ($hour = 0; $hour <= 24; $hour++) {
            [$l, $c, $h] = self::aroundTheClock($hour, $dark);
            $stops[] = sprintf('oklch(%.3f %.3f %.1f) %ddeg', $l, $c, $h, $hour * 15);
        }

        return 'conic-gradient('.implode(', ', $stops).')';
    }

    /** For CSS: "oklch(0.950 0.035 210.0)". */
    public static function css(float $hour, bool $dark = false): string
    {
        [$l, $c, $h] = self::oklch($hour, $dark);

        return sprintf('oklch(%.3f %.3f %.1f)', $l, $c, $h);
    }

    /**
     * For images and email clients that don't know OKLCH.
     *
     * @return array{int, int, int}
     */
    public static function rgb(float $hour, bool $dark = false): array
    {
        return self::oklchToRgb(...self::oklch($hour, $dark));
    }

    public static function hex(float $hour, bool $dark = false): string
    {
        return vsprintf('#%02X%02X%02X', self::rgb($hour, $dark));
    }

    /**
     * OKLCH to sRGB, through OKLab and linear light (Björn Ottosson's matrices).
     *
     * @return array{int, int, int}
     */
    public static function oklchToRgb(float $lightness, float $chroma, float $hue): array
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $linear = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        return array_map(function (float $channel): int {
            $channel = max(0.0, min(1.0, $channel));
            $gamma = $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * ($channel ** (1 / 2.4)) - 0.055;

            return (int) round($gamma * 255);
        }, $linear);
    }

    /** Blend two hues the short way round the colour wheel. */
    private static function mixHue(float $from, float $to, float $t): float
    {
        $delta = fmod($to - $from + 540, 360) - 180;

        return fmod($from + $delta * $t + 360, 360);
    }
}
