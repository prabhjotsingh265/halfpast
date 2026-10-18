<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A team member's photo: cropped to a square from the middle, turned the
 * right way up, made 320 pixels across and saved as WebP, which keeps every
 * photo small however big the original was. The old one is deleted.
 */
class SaveStaffPhoto
{
    public const SIZE = 320;

    /** Upload rules, kept here so every form that takes a photo agrees. */
    public const RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'];

    /**
     * @throws ValidationException
     */
    public function handle(Staff $staff, UploadedFile $file): void
    {
        $image = self::squared($file);

        ob_start();
        imagewebp($image, null, 82);
        $webp = (string) ob_get_clean();

        $path = "staff/{$staff->business_id}/".Str::lower(Str::random(32)).'.webp';
        Storage::disk('public')->put($path, $webp);

        $old = $staff->photo_path;
        $staff->forceFill(['photo_path' => $path])->save();

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }
    }

    public function remove(Staff $staff): void
    {
        if ($staff->photo_path === null) {
            return;
        }

        Storage::disk('public')->delete($staff->photo_path);
        $staff->forceFill(['photo_path' => null])->save();
    }

    /**
     * @throws ValidationException
     */
    private static function squared(UploadedFile $file): GdImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => "That picture couldn't be read. Try a JPG or PNG."]);
        }

        // Phones save which way the camera was held separately from the
        // picture; without this, a portrait shot can come out sideways.
        if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $rotated = match ($orientation) {
                3 => imagerotate($source, 180, 0),
                6 => imagerotate($source, -90, 0),
                8 => imagerotate($source, 90, 0),
                default => $source,
            };
            $source = $rotated === false ? $source : $rotated;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        return $square;
    }
}
