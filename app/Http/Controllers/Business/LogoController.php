<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\SaveBusinessLogo;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Adding, replacing or removing the business's logo. */
class LogoController extends Controller
{
    public function store(Request $request, CurrentBusiness $current, SaveBusinessLogo $logos): RedirectResponse
    {
        $request->validate(['logo' => SaveBusinessLogo::RULES], [
            'logo.required' => 'Choose a picture of your logo.',
            'logo.image' => 'Choose a picture: a PNG, JPG or WebP.',
            'logo.mimes' => 'Choose a picture: a PNG, JPG or WebP.',
            'logo.max' => 'That picture is over 5 MB. Try a smaller one.',
            'logo.dimensions' => 'Use a picture at least 64 pixels across and no more than 6000.',
        ]);

        $logos->handle($current->require(), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo saved.']);

        return back();
    }

    public function destroy(CurrentBusiness $current, SaveBusinessLogo $logos): RedirectResponse
    {
        $logos->remove($current->require());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo removed.']);

        return back();
    }
}
