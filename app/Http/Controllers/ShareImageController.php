<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\ShareImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The pictures link previews show: one for each booking page, and one for
 * Halfpast itself. Each is drawn once and saved; a business's is drawn
 * again when something on it changes.
 */
class ShareImageController extends Controller
{
    public function business(Business $business): BinaryFileResponse
    {
        return $this->image(ShareImage::forBusiness($business));
    }

    public function halfpast(): BinaryFileResponse
    {
        return $this->image(ShareImage::forHalfpast());
    }

    private function image(string $path): BinaryFileResponse
    {
        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
