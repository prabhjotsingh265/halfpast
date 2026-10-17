<?php

namespace App\Mail;

use NumberFormatter;

/**
 * Amounts in emails, formatted the same way the booking pages do:
 * 15000 INR → "₹150", 2550 GBP → "£25.50".
 */
final class Money
{
    public static function format(int $minor, string $currency): string
    {
        $formatter = new NumberFormatter('en', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $minor % 100 === 0 ? 0 : 2);

        return (string) $formatter->formatCurrency($minor / 100, $currency);
    }
}
