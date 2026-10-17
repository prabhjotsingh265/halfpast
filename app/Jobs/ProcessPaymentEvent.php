<?php

namespace App\Jobs;

use App\Actions\Payments\SettlePayment;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Settles the payment a webhook is about. What the payment's status is gets
 * asked of the gateway itself, not taken from the webhook's body.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(SettlePayment $settle, CurrentBusiness $current): void
    {
        $event = PaymentEvent::find($this->eventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        $payment = Payment::withoutGlobalScopes()
            ->where('gateway', $event->gateway)
            ->where('gateway_order_id', (string) ($event->payload['order_id'] ?? ''))
            ->first();

        // Not one of ours (a payment made some other way on the same account).
        if ($payment !== null) {
            // The work happens inside the payment's own business.
            $current->set(Business::find($payment->business_id));
            $settle->handle($payment);
        }

        $event->update(['processed_at' => now()]);
    }
}
