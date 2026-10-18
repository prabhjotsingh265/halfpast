<?php

namespace App\Http\Controllers;

use App\Actions\Booking\HoldAgain;
use App\Actions\Payments\SettlePayment;
use App\Actions\Payments\StartPayment;
use App\Enums\BookingStatus;
use App\Exceptions\PaymentNotStarted;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Payments\GatewayError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paying the deposit: sending the customer to the gateway, and settling the
 * payment when they come back.
 */
class PaymentController extends Controller
{
    public function __construct(
        private StartPayment $start,
        private SettlePayment $settle,
        private HoldAgain $holdAgain,
    ) {}

    public function store(Request $request, Business $business, Booking $booking): Response
    {
        abort_unless(HoldController::heldBy($request, $booking), 404);

        $gateway = (string) $request->validate(['gateway' => ['required', 'string', 'max:20']])['gateway'];

        // The hold was cleared after it ran out: hold the time again if it's
        // still free, and carry on with that.
        if ($booking->status === BookingStatus::Expired) {
            $renewed = $this->holdAgain->handle($business, $booking);

            if ($renewed === null) {
                return $this->backToCheckout($business, $booking, PaymentNotStarted::holdGone()->getMessage());
            }

            $this->start->replaceUnfinished($booking);
            $request->session()->put(HoldController::SESSION_KEY, [$renewed->code]);
            $booking = $renewed;
        }

        try {
            [$payment, $checkout] = $this->start->handle($business, $booking, $gateway);
        } catch (PaymentNotStarted $e) {
            return $this->backToCheckout($business, $booking, $e->getMessage());
        }

        if ($checkout->redirectUrl !== null) {
            return Inertia::location($checkout->redirectUrl);
        }

        // A pop-up gateway (Razorpay) opens on the checkout page itself. Its
        // settings wait on the server for as long as the payment window.
        Cache::put(self::widgetKey($payment->id), $checkout->widget, now()->addMinutes((int) config('payments.payment_window_minutes')));

        return redirect()->route('booking.checkout', [$business, $booking, 'pay' => $payment->id]);
    }

    /**
     * Where the gateway sends the customer back to. What happened is asked of
     * the gateway itself, never read from the address.
     */
    public function show(Request $request, Business $business, Booking $booking, Payment $payment): RedirectResponse
    {
        try {
            $this->settle->handle($payment);
        } catch (GatewayError $e) {
            // The gateway can't be asked right now. The webhook settles it
            // later; meanwhile the checkout shows the payment as processing.
            report($e);
        }

        $booking->refresh();

        if ($booking->status === BookingStatus::Confirmed) {
            return redirect()->to($booking->manageUrl());
        }

        // Back in a different browser (a banking app can do that), where the
        // checkout isn't theirs to see.
        if (! HoldController::heldBy($request, $booking)) {
            return redirect()->route('booking.show', ['business' => $business, 'service' => $booking->service_id]);
        }

        return redirect()->route('booking.checkout', [$business, $booking, 'returned' => 1]);
    }

    private function backToCheckout(Business $business, Booking $booking, string $message): RedirectResponse
    {
        return redirect()->route('booking.checkout', [$business, $booking])->withErrors(['payment' => $message]);
    }

    public static function widgetKey(int $paymentId): string
    {
        return "payments.widget.{$paymentId}";
    }
}
