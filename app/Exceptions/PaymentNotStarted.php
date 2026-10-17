<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment couldn't begin. The message is safe to show the customer.
 */
class PaymentNotStarted extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self("That way of paying isn't available. Please choose another.");
    }

    public static function notReady(): self
    {
        return new self('Please fill in your details first.');
    }

    public static function holdGone(): self
    {
        return new self('Your hold ran out and the time has gone to someone else. Please pick another.');
    }

    public static function heldTooLong(): self
    {
        return new self("You've had this time held for a while, so it's been let go. If it's still free, you can pick it again.");
    }

    public static function gatewayDown(): self
    {
        return new self("We couldn't reach the payment provider. Nothing was taken. Please try again in a moment, or choose another way to pay.");
    }
}
