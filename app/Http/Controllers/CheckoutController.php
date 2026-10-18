<?php

namespace App\Http\Controllers;

use App\Actions\Booking\ConfirmBooking;
use App\Actions\Booking\FindBookableTimes;
use App\Actions\Booking\HoldAgain;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\CustomerDetailsRequest;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Payments\Gateways;
use App\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The step after holding a time: the customer's details, and (for services
 * that take one) the deposit. Only the browser that made the hold can see it.
 */
class CheckoutController extends Controller
{
    public function __construct(private FindBookableTimes $times, private HoldAgain $holdAgain, private Gateways $gateways) {}

    public function show(Request $request, Business $business, Booking $booking): Response|RedirectResponse
    {
        abort_unless(HoldController::heldBy($request, $booking), 404);

        if ($booking->status === BookingStatus::Confirmed) {
            return redirect()->to($booking->manageUrl());
        }

        $booking->load(['staff', 'customer']);
        $payment = Payment::where('booking_id', $booking->id)->latest('id')->first();

        return Inertia::render('booking/Checkout', [
            'booking' => [
                'code' => $booking->code,
                'service_name' => $booking->service_name,
                'service_id' => $booking->service_id,
                'staff_name' => $booking->staff?->name,
                'starts_at' => $booking->starts_at->utc()->toIso8601ZuluString(),
                'ends_at' => $booking->ends_at->utc()->toIso8601ZuluString(),
                'price' => $booking->price,
                'deposit' => $booking->deposit,
                'currency' => $booking->currency,
                'held' => $booking->isHeld(),
                // Lapsed, but nobody has taken the time, so it can still be booked.
                'renewable' => $booking->status === BookingStatus::Held || $this->stillFree($business, $booking),
                'confirmed' => false,
                'hold_expires_at' => $booking->hold_expires_at?->utc()->toIso8601ZuluString(),
            ],
            'customer' => $booking->customer === null ? null : [
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
                'notes' => $booking->notes,
            ],
            'gateways' => array_values(array_map(
                fn (PaymentGateway $gateway) => ['key' => $gateway->key(), 'label' => $gateway->label(), 'test_mode' => $gateway->isTestMode()],
                $this->gateways->for($business),
            )),
            // Planned but not set up yet, shown as "coming soon".
            'upcoming' => array_values(array_map(
                fn (PaymentGateway $gateway) => ['key' => $gateway->key(), 'label' => $gateway->label()],
                array_diff_key($this->gateways->upcoming(), $this->gateways->for($business)),
            )),
            // The pop-up to open straight away, after "Pay" with Razorpay.
            'widget' => $this->widget($request, $payment),
            'payment' => $payment === null ? null : [
                'status' => $payment->status->value,
                'outcome' => $payment->outcome?->value,
                'failure_reason' => $payment->failure_reason,
                // Money already taken comes back as a refund; otherwise it was never taken.
                'refunded' => $payment->captured_at !== null,
            ],
            // Back from the gateway without paying, or back to wait for it.
            'cancelled' => $request->boolean('cancelled'),
            'returned' => $request->boolean('returned'),
            'holdMinutes' => $business->hold_minutes,
            'paymentWindow' => (int) config('payments.payment_window_minutes'),
            // The browser's clock may be off; the countdown runs from the server's.
            'serverNow' => now()->utc()->toIso8601ZuluString(),
        ]);
    }

    /**
     * Saves who the booking is for. With nothing to pay, that confirms it;
     * with a deposit, the next step is paying it.
     */
    public function store(CustomerDetailsRequest $request, Business $business, Booking $booking, ConfirmBooking $confirm): RedirectResponse
    {
        abort_unless(HoldController::heldBy($request, $booking), 404);

        if ($booking->status === BookingStatus::Confirmed) {
            return redirect()->to($booking->manageUrl());
        }

        // The hold was cleared after it ran out. If the time is still free,
        // hold it again rather than send the customer back to the start.
        if ($booking->status === BookingStatus::Expired) {
            $booking = $this->holdAgain($request, $business, $booking);
        }

        if ($booking === null || $booking->status !== BookingStatus::Held) {
            return back()->withErrors(['booking' => 'Your hold ran out and the time has gone to someone else. Please pick another.']);
        }

        DB::transaction(function () use ($request, $booking) {
            $email = mb_strtolower(trim((string) $request->validated('email')));
            $details = [
                'name' => (string) $request->validated('name'),
                'phone' => $request->validated('phone'),
            ];

            // One record per email address, made safely even if two bookings
            // with the same address arrive at once.
            $customer = Customer::createOrFirst(['email' => $email], $details);
            $customer->update(array_filter($details, fn ($value) => $value !== null));

            $booking->update(['customer_id' => $customer->id, 'notes' => $request->validated('notes')]);
        });

        if ($booking->deposit > 0) {
            return redirect()->route('booking.checkout', [$business, $booking]);
        }

        if (! $confirm->handle($booking)) {
            return back()->withErrors(['booking' => 'Your hold ran out and the time has gone to someone else. Please pick another.']);
        }

        return redirect()->to($booking->manageUrl());
    }

    /** Whether a cleared hold's time could still be booked, with the same person. */
    private function stillFree(Business $business, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Expired) {
            return false;
        }

        $service = Service::find($booking->service_id);
        $staff = Staff::find($booking->staff_id);

        if ($service === null || $staff === null) {
            return false;
        }

        $date = $booking->starts_at->setTimezone($business->timezone)->toDateString();

        return collect($this->times->handle($business, $service, $staff, $date, $date))
            ->contains(fn ($slot) => $slot->start->getTimestamp() === $booking->starts_at->getTimestamp());
    }

    /** Holds the cleared time again, if it's still free, as this browser's hold. */
    private function holdAgain(Request $request, Business $business, Booking $expired): ?Booking
    {
        $booking = $this->holdAgain->handle($business, $expired);

        if ($booking !== null) {
            $request->session()->put(HoldController::SESSION_KEY, [$booking->code]);
        }

        return $booking;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function widget(Request $request, ?Payment $latest): ?array
    {
        $paymentId = $request->integer('pay');

        if ($paymentId === 0 || $latest === null || $latest->id !== $paymentId || $latest->status !== PaymentStatus::Pending) {
            return null;
        }

        $widget = Cache::get(PaymentController::widgetKey($paymentId));

        return is_array($widget) ? $widget : null;
    }
}
