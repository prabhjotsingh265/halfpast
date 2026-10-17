<?php

namespace App\Payments;

/**
 * A payment as the gateway sees it.
 */
final readonly class GatewayStatus
{
    public function __construct(
        public GatewayState $state,
        /** The gateway's payment id (PaymentIntent, capture, payment). */
        public ?string $paymentId = null,
        /** Minor units, as the gateway reports them. */
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $reason = null,
    ) {}

    public function hasMoney(): bool
    {
        return in_array($this->state, [GatewayState::Authorized, GatewayState::Captured], true);
    }
}
