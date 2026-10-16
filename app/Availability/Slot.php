<?php

namespace App\Availability;

use DateTimeImmutable;

/**
 * A time a customer can book, and who could see them then.
 */
final readonly class Slot
{
    /**
     * @param  list<int>  $staffIds
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public array $staffIds,
    ) {}
}
