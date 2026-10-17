<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use Illuminate\Support\Facades\DB;

/**
 * Sends a refund to the gateway and records it once it's accepted. Each
 * refund carries its own idempotency key, so retrying after a timeout can't
 * refund twice.
 */
class RefundPayment
{
    /** Tries before a refund is marked failed for someone to look at. */
    public const MAX_ATTEMPTS = 6;

    public function __construct(private Gateways $gateways) {}

    /**
     * @throws GatewayError The refund stays pending to be tried again.
     */
    public function handle(Refund $refund): Refund
    {
        $refund->refresh();

        if ($refund->status !== RefundStatus::Pending) {
            return $refund;
        }

        $payment = Payment::withoutGlobalScopes()->findOrFail($refund->payment_id);

        try {
            $gatewayRefundId = $this->gateways->get($payment->gateway)->refund($payment, $refund->amount, 'halfpast-refund-'.$refund->id);
        } catch (GatewayError $e) {
            $attempts = $refund->attempts + 1;
            $refund->update([
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
                'status' => $attempts >= self::MAX_ATTEMPTS ? RefundStatus::Failed : RefundStatus::Pending,
            ]);

            throw $e;
        }

        DB::transaction(function () use ($refund, $gatewayRefundId) {
            $locked = Refund::withoutGlobalScopes()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status === RefundStatus::Succeeded) {
                return;
            }

            $locked->update(['status' => RefundStatus::Succeeded, 'gateway_refund_id' => $gatewayRefundId, 'attempts' => $locked->attempts + 1, 'last_error' => null]);

            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->payment_id);
            $refunded = $payment->refunded_amount + $locked->amount;
            $payment->update([
                'refunded_amount' => $refunded,
                'status' => $refunded >= $payment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);

            $entry = new LedgerEntry;
            $entry->forceFill([
                'business_id' => $payment->business_id,
                'payment_id' => $payment->id,
                'refund_id' => $locked->id,
                'type' => 'refund',
                'amount' => -$locked->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
            ])->save();
        });

        return $refund->refresh();
    }
}
