<?php

namespace App\Availability;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * What's being looked for: a service of a given length, on some local dates,
 * no sooner than `earliest` and no later than `latest`.
 */
final readonly class SlotRequest
{
    public DateTimeZone $zone;

    public function __construct(
        string $timezone,
        /** First local date, "2026-11-03". */
        public string $from,
        /** Last local date, included. */
        public string $to,
        public int $durationMinutes,
        public int $bufferMinutes,
        public int $stepMinutes,
        /** Usually now plus the business's minimum notice. */
        public DateTimeImmutable $earliest,
        /** Usually now plus how far ahead the business takes bookings. */
        public DateTimeImmutable $latest,
    ) {
        $this->zone = new DateTimeZone($timezone);

        if ($durationMinutes < 1 || $stepMinutes < 1 || $bufferMinutes < 0) {
            throw new InvalidArgumentException('Durations and steps have to be positive.');
        }

        if ($to < $from) {
            throw new InvalidArgumentException('The last date is before the first.');
        }
    }
}
