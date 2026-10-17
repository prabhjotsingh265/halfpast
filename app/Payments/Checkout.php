<?php

namespace App\Payments;

/**
 * How the customer pays: sent to the gateway's own page, or with a pop-up
 * on ours (Razorpay), configured by `widget`.
 */
final readonly class Checkout
{
    /**
     * @param  array<string, mixed>  $widget
     */
    public function __construct(
        public ?string $redirectUrl = null,
        public array $widget = [],
    ) {}
}
