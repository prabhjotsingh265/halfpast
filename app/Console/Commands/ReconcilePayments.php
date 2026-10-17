<?php

namespace App\Console\Commands;

use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\FinishPayment;
use App\Jobs\SendRefund;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Refund;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use App\Tenancy\CurrentBusiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The safety net under webhooks. Runs every minute and asks the gateways
 * about payments nobody has heard back about: the customer paid and closed
 * the tab, and the webhook never arrived. It also restarts captures and
 * refunds whose retry job went missing, and gives up on checkouts left
 * unpaid for a day.
 */
#[Signature('payments:reconcile')]
#[Description('Settle payments whose webhook never came, and restart stuck captures and refunds')]
class ReconcilePayments extends Command
{
    /** Give the customer this long to come back by themselves first. */
    public const QUIET_MINUTES = 2;

    /** Ask the gateway about the same payment at most this often. */
    public const RECHECK_MINUTES = 2;

    /** An unpaid checkout older than this is given up on. */
    public const ABANDON_HOURS = 24;

    public function handle(SettlePayment $settle, Gateways $gateways, CurrentBusiness $current): int
    {
        $settled = 0;

        Payment::withoutGlobalScopes()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(self::QUIET_MINUTES))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subMinutes(self::RECHECK_MINUTES)))
            ->whereNotNull('gateway_order_id')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (Payment $payment) use ($settle, $gateways, $current, &$settled) {
                $current->set(Business::find($payment->business_id));
                $payment->update(['checked_at' => now()]);

                try {
                    $after = $settle->handle($payment);
                } catch (GatewayError $e) {
                    report($e);

                    return;
                }

                if ($after->status !== PaymentStatus::Pending) {
                    $settled++;

                    return;
                }

                // Still unpaid after a day: stop the checkout and give up on it.
                if ($payment->created_at?->lessThanOrEqualTo(now()->subHours(self::ABANDON_HOURS))) {
                    try {
                        $gateways->get($payment->gateway)->release($payment);
                    } catch (GatewayError $e) {
                        report($e);
                    }

                    Payment::withoutGlobalScopes()->whereKey($payment->id)->where('status', PaymentStatus::Pending)
                        ->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'abandoned']);
                }
            });

        // Decided but not finished, with no retry on the way (a lost job).
        $stuck = Payment::withoutGlobalScopes()
            ->where('status', PaymentStatus::Authorized)
            ->whereNotNull('outcome')
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->pluck('id');
        $stuck->each(fn (int $id) => FinishPayment::dispatch($id));

        $refunds = Refund::withoutGlobalScopes()
            ->where('status', RefundStatus::Pending)
            ->where('attempts', '<', RefundPayment::MAX_ATTEMPTS)
            ->where('updated_at', '<=', now()->subMinutes(30))
            ->pluck('id');
        $refunds->each(fn (int $id) => SendRefund::dispatch($id));

        $this->info("Settled {$settled}; restarted {$stuck->count()} payments and {$refunds->count()} refunds.");

        return self::SUCCESS;
    }
}
