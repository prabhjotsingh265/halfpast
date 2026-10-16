<?php

namespace App\Http\Controllers;

use App\Actions\Businesses\CreateBusiness;
use App\Http\Middleware\SetCurrentBusiness;
use App\Http\Requests\StoreBusinessRequest;
use App\Models\Business;
use App\Models\User;
use App\Rules\BookingAddress;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /** Where the name typed on the home page waits until the business is created. */
    public const SUGGESTED_NAME = 'onboarding.business_name';

    public function create(Request $request, CurrentBusiness $current): Response|RedirectResponse
    {
        if ($current->get() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/CreateBusiness', [
            'suggestedName' => $request->session()->get(self::SUGGESTED_NAME, ''),
            'currencies' => Business::CURRENCIES,
            'bookingBase' => preg_replace('#^https?://#', '', rtrim(url('/'), '/')).'/',
        ]);
    }

    public function store(StoreBusinessRequest $request, CreateBusiness $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, slug: string, timezone: string, currency: string} $details */
        $details = $request->validated();

        $business = $create->handle($user, $details);

        $request->session()->forget(self::SUGGESTED_NAME);
        $request->session()->put(SetCurrentBusiness::SESSION_KEY, $business->id);

        return redirect()->route('dashboard');
    }

    /**
     * Live check while the address is typed, so problems show before submitting.
     */
    public function checkAddress(Request $request): JsonResponse
    {
        $slug = (string) $request->query('slug', '');

        $validator = Validator::make(['slug' => $slug], ['slug' => [new BookingAddress]]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'message' => $validator->errors()->first('slug')]);
        }

        if (Business::where('slug', $slug)->exists()) {
            return response()->json(['available' => false, 'message' => 'Another business already has that address.']);
        }

        return response()->json(['available' => true, 'message' => 'Available']);
    }
}
