<?php

namespace App\Availability;

/**
 * A set of non-overlapping intervals kept in order, merged wherever they touch.
 */
final readonly class IntervalSet
{
    /** @var list<Interval> */
    public array $intervals;

    /**
     * @param  iterable<Interval>  $intervals
     */
    public function __construct(iterable $intervals = [])
    {
        $sorted = [];

        foreach ($intervals as $interval) {
            if ($interval->end > $interval->start) {
                $sorted[] = $interval;
            }
        }

        usort($sorted, fn (Interval $a, Interval $b) => $a->start <=> $b->start);

        $merged = [];

        foreach ($sorted as $interval) {
            $last = end($merged);

            if ($last !== false && $interval->start <= $last->end) {
                $merged[array_key_last($merged)] = new Interval($last->start, max($last->end, $interval->end));
            } else {
                $merged[] = $interval;
            }
        }

        $this->intervals = $merged;
    }

    public function isEmpty(): bool
    {
        return $this->intervals === [];
    }

    public function union(self $other): self
    {
        return new self([...$this->intervals, ...$other->intervals]);
    }

    /** What's left of this set once the other set is taken out of it. */
    public function subtract(self $other): self
    {
        $result = [];

        foreach ($this->intervals as $interval) {
            $start = $interval->start;

            foreach ($other->intervals as $cut) {
                if ($cut->end <= $start || $cut->start >= $interval->end) {
                    continue;
                }

                if ($cut->start > $start) {
                    $result[] = new Interval($start, $cut->start);
                }

                $start = max($start, $cut->end);
            }

            if ($start < $interval->end) {
                $result[] = new Interval($start, $interval->end);
            }
        }

        return new self($result);
    }

    /** Whether one interval in the set covers all of the one given. */
    public function covers(Interval $interval): bool
    {
        foreach ($this->intervals as $candidate) {
            if ($candidate->contains($interval)) {
                return true;
            }
        }

        return false;
    }

    public function overlaps(Interval $interval): bool
    {
        foreach ($this->intervals as $candidate) {
            if ($candidate->overlaps($interval)) {
                return true;
            }
        }

        return false;
    }
}
