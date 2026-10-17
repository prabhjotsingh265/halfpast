<?php

namespace App\Payments;

/**
 * A verified webhook, reduced to what's needed to find and settle the payment.
 */
final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        /** The gateway's order id, which our payment row was created with. */
        public string $orderId,
        public array $payload = [],
    ) {}
}
