<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A business's logo, for its booking page, emails and share image. Logos
 * come in every shape, so it keeps its own: empty margins trimmed, then
 * scaled down until the longer side is 256 pixels, with any transparency
 * kept, saved as PNG because every email client can show one. The old logo
 * is deleted.
 */
class SaveBusinessLogo
{
    public const SIZE = 256;

    /** No SVG: a drawing can carry script, and this one is shown to the public. */
    public const RULES = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000'];

    /**
     * @throws ValidationException
     */
    public function handle(Business $business, UploadedFile $file): void
    {
        $image = self::fitted($file);

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();

        $path = "logos/{$business->id}/".Str::lower(Str::random(32)).'.png';
        Storage::disk('public')->put($path, $png);

        $old = $business->logo_path;
        $business->forceFill(['logo_path' => $path])->save();

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }
    }

    public function remove(Business $business): void
    {
        if ($business->logo_path === null) {
            return;
        }

        Storage::disk('public')->delete($business->logo_path);
        $business->forceFill(['logo_path' => null])->save();
    }

    /**
     * @throws ValidationException
     */
    private static function fitted(UploadedFile $file): GdImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['logo' => "That picture couldn't be read. Try a PNG or JPG."]);
        }

        // A phone photo of a sign can be stored sideways; EXIF says which way is up.
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

        $source = self::trimmed($source);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::SIZE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $fitted = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($fitted, false);
        imagesavealpha($fitted, true);
        imagefill($fitted, 0, 0, (int) imagecolorallocatealpha($fitted, 0, 0, 0, 127));
        imagecopyresampled($fitted, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $fitted;
    }

    /**
     * Without the empty margin exports often leave round a logo: edges the
     * colour of its corners, whether see-through or a background like white.
     * (GD's own "transparent" mode misses alpha PNGs; matching the corners
     * catches them, since exported see-through pixels are all the same.)
     */
    private static function trimmed(GdImage $image): GdImage
    {
        $cropped = @imagecropauto($image, IMG_CROP_SIDES);

        // Nothing to trim, or nothing left after trimming (a blank picture).
        if ($cropped === false || imagesx($cropped) < 8 || imagesy($cropped) < 8) {
            return $image;
        }

        return $cropped;
    }
}
