<?php

namespace App\Payments;

final readonly class CheckoutLinks
{
    public function __construct(
        /** Where the customer comes back to after paying. */
        public string $return,
        /** Where they come back to if they cancel at the gateway. */
        public string $cancel,
    ) {}
}
