<?php

namespace App\Jobs;

use App\Actions\Payments\RefundPayment;
use App\Enums\RefundStatus;
use App\Models\Business;
use App\Models\Refund;
use App\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Tries a refund again after the gateway refused or timed out.
 */
class SendRefund implements ShouldQueue
{
    use Queueable;

    public int $tries = RefundPayment::MAX_ATTEMPTS;

    public function __construct(public int $refundId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(RefundPayment $refunds, CurrentBusiness $current): void
    {
        $refund = Refund::withoutGlobalScopes()->find($this->refundId);

        if ($refund === null || $refund->status !== RefundStatus::Pending) {
            return;
        }

        $current->set(Business::find($refund->business_id));
        $refunds->handle($refund);
    }

    public function failed(?Throwable $exception): void
    {
        Refund::withoutGlobalScopes()->whereKey($this->refundId)
            ->where('status', RefundStatus::Pending)
            ->update(['status' => RefundStatus::Failed]);

        report(new RuntimeException("Refund {$this->refundId} still failed after every retry and needs looking at.", previous: $exception));
    }
}
