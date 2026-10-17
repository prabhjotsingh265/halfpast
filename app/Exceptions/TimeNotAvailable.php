<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The time asked for can't be held: someone took it a moment earlier, or it
 * was never free (too soon, outside working hours).
 */
class TimeNotAvailable extends RuntimeException
{
    public static function taken(): self
    {
        return new self('Someone has just taken that time.');
    }

    public static function notOffered(): self
    {
        return new self("That time isn't free any more.");
    }
}
