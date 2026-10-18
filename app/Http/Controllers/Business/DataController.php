<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\DeleteBusiness;
use App\Actions\Businesses\ExportBusinessData;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\Booking;
use App\Models\Customer;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The business's own data: everything in it to download, and deleting the
 * business for good.
 */
class DataController extends Controller
{
    public function edit(CurrentBusiness $current): Response
    {
        $business = $current->require();

        return Inertia::render('business/Data', [
            'name' => $business->name,
            'isDemo' => $business->is_demo,
            'counts' => [
                'bookings' => Booking::count(),
                'customers' => Customer::whereNull('forgotten_at')->count(),
            ],
            'upcoming' => Booking::where('starts_at', '>', now())->where('status', BookingStatus::Confirmed)->count(),
        ]);
    }

    public function export(CurrentBusiness $current, ExportBusinessData $export): BinaryFileResponse
    {
        $business = $current->require();
        $file = Str::slug($business->name).'-halfpast-'.now($business->timezone)->format('Y-m-d').'.zip';

        return response()->download($export->handle($business), $file, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function destroy(Request $request, CurrentBusiness $current, DeleteBusiness $delete): RedirectResponse
    {
        $business = $current->require();

        $request->validate([
            'name' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($business) {
                if (mb_strtolower(trim((string) $value)) !== mb_strtolower(trim($business->name))) {
                    $fail("Type the business's name exactly, to be sure.");
                }
            }],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => "That isn't your password."]);

        $name = $business->name;
        $delete->handle($business);

        $request->session()->forget(SetCurrentBusiness::SESSION_KEY);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} is deleted, with everything in it."]);

        return redirect()->route('dashboard');
    }
}
