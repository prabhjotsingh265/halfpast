<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Staff;
use App\Models\WorkingHour;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * The picture shown when a booking page is shared: on WhatsApp, in iMessage,
 * on social sites. Each business's is its own, because the background is its
 * working day: the daylight colours running from its opening hour to its
 * closing hour, with the hours marked along the bottom. Its logo (or initial),
 * its name and its description sit on top.
 *
 * Drawn at twice the size and scaled down, since GD doesn't smooth the edges
 * of shapes, only of text.
 */
final class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Bump to redraw every saved image after a design change. */
    private const VERSION = 1;

    private const SCALE = 2;

    private const INK = [21, 24, 31];

    private const INK_2 = [81, 85, 94];

    private const SUN = [239, 145, 45];

    /** A fingerprint of everything drawn, so a changed business gets a new image. */
    public static function key(Business $business): string
    {
        [$opens, $closes] = self::workingDay($business);

        return substr(md5((string) json_encode([self::VERSION, $business->name, $business->description, $business->logo_path, $opens, $closes])), 0, 12);
    }

    /** The saved image's path on the public disk, drawn first if need be. */
    public static function forBusiness(Business $business): string
    {
        $path = "share/{$business->id}/".self::key($business).'.png';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            [$opens, $closes] = self::workingDay($business);
            $logo = $business->logo_path && $disk->exists($business->logo_path) ? $disk->get($business->logo_path) : null;

            // Only the current picture is kept.
            $disk->delete($disk->files("share/{$business->id}"));
            $disk->put($path, self::draw($business->name, $business->description, $opens, $closes, logo: $logo));
        }

        return $path;
    }

    /** Halfpast's own, for the home page and the rest of the site. */
    public static function forHalfpast(): string
    {
        $path = 'share/halfpast-v'.self::VERSION.'.png';

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, self::draw(
                'Bookings that show up.',
                'Booking pages that take deposits, send the reminders and never double-book.',
                6.0,
                21.0,
                halfpast: true,
            ));
        }

        return $path;
    }

    /**
     * The earliest anyone starts and the latest anyone finishes, on a usual
     * week; nine to six for a business with no hours yet.
     *
     * @return array{float, float}
     */
    public static function workingDay(Business $business): array
    {
        $active = Staff::withoutGlobalScopes()->where('business_id', $business->id)->where('is_active', true)->pluck('id');
        $hours = WorkingHour::withoutGlobalScopes()->whereIn('staff_id', $active)->get(['starts_at', 'ends_at']);

        if ($hours->isEmpty()) {
            return [9.0, 18.0];
        }

        $toHours = fn (string $time) => (int) substr($time, 0, 2) + (int) substr($time, 3, 2) / 60;
        $opens = (float) $hours->min(fn (WorkingHour $hour) => $toHours($hour->starts_at));
        $closes = (float) $hours->max(fn (WorkingHour $hour) => $toHours($hour->ends_at));

        // At least a few hours wide, so the colours have room to change.
        return $closes - $opens < 4 ? [max(0.0, $opens - 2), min(24.0, $closes + 2)] : [$opens, $closes];
    }

    /** PNG bytes. */
    public static function draw(string $name, ?string $description, float $opens, float $closes, ?string $logo = null, bool $halfpast = false): string
    {
        $s = self::SCALE;
        $width = self::WIDTH * $s;
        $height = self::HEIGHT * $s;
        $canvas = imagecreatetruecolor($width, $height);

        // The working day, left to right.
        for ($x = 0; $x < $width; $x++) {
            $hour = $opens + ($closes - $opens) * $x / ($width - 1);
            imageline($canvas, $x, 0, $x, $height, self::color($canvas, Daylight::rgb($hour)));
        }

        self::hourScale($canvas, $opens, $closes);

        $margin = 72 * $s;
        $tile = 112 * $s;

        if ($halfpast) {
            self::roundedRectangle($canvas, $margin, $margin, $tile, $tile, 28 * $s, [251, 250, 247]);
            self::mark($canvas, $margin + $tile / 2, $margin + $tile / 2, (int) round($tile * 0.36));
        } else {
            self::logoTile($canvas, $margin, $margin, $tile, $name, $logo);
            self::badge($canvas, $width - $margin, $margin + $tile / 2);
        }

        $textWidth = $width - 2 * $margin;
        $y = $margin + $tile + 72 * $s;
        $y = self::paragraph($canvas, $name, 'Recursive-CasualExtraBold', 66 * $s, self::INK, $margin, $y, $textWidth, 2, 1.08);

        if ($description) {
            self::paragraph($canvas, $description, 'Recursive-Regular', 30 * $s, self::INK_2, $margin, $y + 20 * $s, (int) ($textWidth * 0.86), 2, 1.38);
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($image, $canvas, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $width, $height);

        ob_start();
        imagepng($image, null, 8);

        return (string) ob_get_clean();
    }

    /** Ticks and labels for each hour along the bottom, like a day view. */
    private static function hourScale(GdImage $canvas, float $opens, float $closes): void
    {
        $s = self::SCALE;
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $baseline = $height - 40 * $s;
        $line = self::color($canvas, self::INK, 0.82);
        $label = self::color($canvas, self::INK_2);
        $span = $closes - $opens;
        $every = $span > 12 ? 3 : ($span > 8 ? 2 : 1);

        imagefilledrectangle($canvas, 0, $baseline - 64 * $s, $width, $baseline - 63 * $s, $line);

        for ($hour = (int) ceil($opens); $hour <= (int) floor($closes); $hour++) {
            $x = (int) round(($hour - $opens) / $span * ($width - 1));
            $labelled = $hour % $every === 0;
            imagefilledrectangle($canvas, $x - $s, $baseline - 64 * $s, $x + $s - 1, $baseline - ($labelled ? 44 : 54) * $s, $line);

            if ($labelled && $x > 40 * $s && $x < $width - 40 * $s) {
                $text = sprintf('%02d:00', $hour % 24);
                $box = imagettfbbox(19 * $s, 0, self::font('Recursive-MonoMedium'), $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                imagettftext($canvas, 19 * $s, 0, $x - (int) (($box[2] - $box[0]) / 2), $baseline, $label, self::font('Recursive-MonoMedium'), $text);
            }
        }
    }

    /** The logo on a white tile, or the initial on the sun colour. */
    private static function logoTile(GdImage $canvas, int $x, int $y, int $size, string $name, ?string $logo): void
    {
        $s = self::SCALE;
        $source = $logo ? @imagecreatefromstring($logo) : false;

        if ($source === false) {
            self::roundedRectangle($canvas, $x, $y, $size, $size, 28 * $s, self::SUN);
            $initial = mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: '?';
            $font = self::font('Recursive-CasualExtraBold');
            $box = imagettfbbox(60 * $s, 0, $font, $initial) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            imagettftext($canvas, 60 * $s, 0, $x + (int) (($size - ($box[2] - $box[0])) / 2) - $box[0], $y + (int) (($size - ($box[1] - $box[7])) / 2) - $box[7], self::color($canvas, self::INK), $font, $initial);

            return;
        }

        self::roundedRectangle($canvas, $x, $y, $size, $size, 28 * $s, [255, 255, 255]);
        $inner = (int) ($size * 0.76);
        $ratio = min($inner / imagesx($source), $inner / imagesy($source));
        $w = (int) round(imagesx($source) * $ratio);
        $h = (int) round(imagesy($source) * $ratio);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $x + (int) (($size - $w) / 2), $y + (int) (($size - $h) / 2), 0, 0, $w, $h, imagesx($source), imagesy($source));
    }

    /** "Book on Halfpast", with the mark, at the top right. */
    private static function badge(GdImage $canvas, int $right, int $middle): void
    {
        $s = self::SCALE;
        $font = self::font('Recursive-SemiBold');
        $text = 'Book on Halfpast';
        $box = imagettfbbox(22 * $s, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $textWidth = $box[2] - $box[0];
        imagettftext($canvas, 22 * $s, 0, $right - $textWidth, $middle + 8 * $s, self::color($canvas, self::INK), $font, $text);
        self::mark($canvas, $right - $textWidth - 30 * $s, $middle, 15 * $s);
    }

    /** The Halfpast mark: a clock at half past, its right half taken. */
    private static function mark(GdImage $canvas, int $cx, int $cy, int $radius): void
    {
        $ink = self::color($canvas, self::INK);
        $stroke = max(2, (int) round($radius * 0.21));

        imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $ink);
        imagefilledellipse($canvas, $cx, $cy, ($radius - $stroke) * 2, ($radius - $stroke) * 2, self::color($canvas, [251, 250, 247]));
        imagefilledarc($canvas, $cx, $cy, ($radius - $stroke) * 2, ($radius - $stroke) * 2, 270, 90, self::color($canvas, self::SUN), IMG_ARC_PIE);
        imagefilledrectangle($canvas, $cx - (int) ($stroke / 2), $cy - $radius + 1, $cx + (int) ceil($stroke / 2) - 1, $cy + $radius - 1, $ink);
    }

    /**
     * Wrapped text, at most so many lines, the last cut short with "…" if
     * it doesn't fit. Returns where the next line would start.
     *
     * @param  array{int, int, int}  $rgb
     */
    private static function paragraph(GdImage $canvas, string $text, string $font, int $size, array $rgb, int $x, int $y, int $maxWidth, int $maxLines, float $leading): int
    {
        $path = self::font($font);
        $measure = function (string $line) use ($size, $path): int {
            $box = imagettfbbox($size, 0, $path, $line) ?: [0, 0, 0, 0, 0, 0, 0, 0];

            return $box[2] - $box[0];
        };

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($measure($candidate) <= $maxWidth || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];

            while (mb_strlen($last) > 1 && $measure($last.'…') > $maxWidth) {
                $last = mb_substr($last, 0, -1);
            }

            $lines[$maxLines - 1] = rtrim($last, ' ,.;:-').'…';
        }

        $color = self::color($canvas, $rgb);
        $lineHeight = (int) round($size * $leading * 1.33);

        foreach ($lines as $line) {
            $y += $lineHeight;
            imagettftext($canvas, $size, 0, $x, $y, $color, $path, $line);
        }

        return $y;
    }

    /** @param  array{int, int, int}  $rgb */
    private static function roundedRectangle(GdImage $canvas, int $x, int $y, int $width, int $height, int $radius, array $rgb): void
    {
        $color = self::color($canvas, $rgb);
        imagefilledrectangle($canvas, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledrectangle($canvas, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);

        foreach ([[$x + $radius, $y + $radius], [$x + $width - $radius, $y + $radius], [$x + $radius, $y + $height - $radius], [$x + $width - $radius, $y + $height - $radius]] as [$cx, $cy]) {
            imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }

    /** @param  array{int, int, int}  $rgb */
    private static function color(GdImage $canvas, array $rgb, float $transparency = 0.0): int
    {
        $channel = fn (int $value): int => max(0, min(255, $value));

        return (int) imagecolorallocatealpha($canvas, $channel($rgb[0]), $channel($rgb[1]), $channel($rgb[2]), max(0, min(127, (int) round($transparency * 127))));
    }

    private static function font(string $name): string
    {
        return resource_path("fonts/{$name}.ttf");
    }
}
