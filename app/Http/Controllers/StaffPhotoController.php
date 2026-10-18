<?php

namespace App\Http\Controllers;

use App\Actions\Staff\SaveStaffPhoto;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Adding or removing a team member's photo, straight from their page.
 */
class StaffPhotoController extends Controller
{
    public function store(Request $request, Staff $staff, SaveStaffPhoto $photos): RedirectResponse
    {
        $request->validate(['photo' => ['required', ...array_slice(SaveStaffPhoto::RULES, 1)]], self::messages());

        $photos->handle($staff, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photo saved.']);

        return back();
    }

    public function destroy(Staff $staff, SaveStaffPhoto $photos): RedirectResponse
    {
        $photos->remove($staff);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photo removed.']);

        return back();
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'photo.image' => 'Choose a picture: a JPG, PNG or WebP.',
            'photo.mimes' => 'Choose a picture: a JPG, PNG or WebP.',
            'photo.max' => 'That picture is over 10 MB. Try a smaller one.',
            'photo.dimensions' => 'Use a picture at least 100 pixels across and no more than 6000.',
        ];
    }
}
