<?php

namespace App\Availability;

use InvalidArgumentException;

/**
 * Someone's usual week as local clock times: ISO weekday (1 is Monday) to a
 * list of [start, end] in minutes since midnight. An end of 1440 is midnight.
 */
final readonly class WeeklySchedule
{
    /**
     * @param  array<int, list<array{int, int}>>  $days
     */
    public function __construct(public array $days)
    {
        foreach ($days as $weekday => $stretches) {
            if ($weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException("There is no weekday {$weekday}.");
            }

            foreach ($stretches as [$start, $end]) {
                if ($start < 0 || $end > 1440 || $end <= $start) {
                    throw new InvalidArgumentException("{$start}–{$end} is not a stretch of one day.");
                }
            }
        }
    }

    /**
     * From rows like the working_hours table: ["weekday" => 2, "starts_at" => "09:00", "ends_at" => "13:00"].
     *
     * @param  iterable<array{weekday: int, starts_at: string, ends_at: string}>  $rows
     */
    public static function fromRows(iterable $rows): self
    {
        $days = [];

        foreach ($rows as $row) {
            $days[$row['weekday']][] = [self::minutes($row['starts_at']), self::minutes($row['ends_at'])];
        }

        foreach ($days as &$stretches) {
            usort($stretches, fn (array $a, array $b) => $a[0] <=> $b[0]);
        }

        return new self($days);
    }

    /**
     * @return list<array{int, int}>
     */
    public function on(int $weekday): array
    {
        return $this->days[$weekday] ?? [];
    }

    /** "09:30" or "09:30:00" → 570 */
    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
