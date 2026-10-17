<?php

namespace App\Actions\Payments;

use App\Jobs\ProcessPaymentEvent;
use App\Models\PaymentEvent;
use App\Payments\Gateways;
use App\Payments\InvalidWebhook;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Takes in a webhook: checks its signature, stores it once, and leaves the
 * settling to a job so the gateway gets its answer straight away. Gateways
 * resend webhooks, often several times, so a repeat is acknowledged and
 * otherwise ignored.
 */
class ReceiveWebhook
{
    public function __construct(private Gateways $gateways) {}

    /**
     * @return int The HTTP status to answer with.
     */
    public function handle(string $gatewayKey, Request $request): int
    {
        $gateway = $this->gateways->all()[$gatewayKey] ?? null;

        if ($gateway === null) {
            return 404;
        }

        try {
            $event = $gateway->webhook($request);
        } catch (InvalidWebhook $e) {
            report($e);

            return 400;
        }

        // An event this app doesn't act on.
        if ($event === null) {
            return 200;
        }

        try {
            // In its own savepoint, so a refused duplicate never spoils a
            // surrounding transaction.
            $stored = DB::transaction(fn () => PaymentEvent::create([
                'gateway' => $gatewayKey,
                'event_id' => $event->id,
                'type' => $event->type,
                'payload' => $event->payload + ['order_id' => $event->orderId],
            ]));
        } catch (UniqueConstraintViolationException) {
            // Seen before. If it never finished processing, give it another go.
            $stored = PaymentEvent::where('gateway', $gatewayKey)->where('event_id', $event->id)->first();

            if ($stored === null || $stored->processed_at !== null) {
                return 200;
            }
        }

        ProcessPaymentEvent::dispatch($stored->id);

        return 200;
    }
}
