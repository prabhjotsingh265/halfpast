<?php

namespace App\Availability;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A stretch of real time, as Unix seconds. The start is included and the end
 * isn't, so 09:00–10:00 and 10:00–11:00 touch without overlapping.
 */
final readonly class Interval
{
    public function __construct(
        public int $start,
        public int $end,
    ) {
        if ($end < $start) {
            throw new InvalidArgumentException('An interval cannot end before it starts.');
        }
    }

    public static function between(DateTimeInterface $start, DateTimeInterface $end): self
    {
        return new self($start->getTimestamp(), $end->getTimestamp());
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }

    public function startsAt(): DateTimeImmutable
    {
        return (new DateTimeImmutable)->setTimestamp($this->start);
    }

    public function endsAt(): DateTimeImmutable
    {
        return (new DateTimeImmutable)->setTimestamp($this->end);
    }
}
