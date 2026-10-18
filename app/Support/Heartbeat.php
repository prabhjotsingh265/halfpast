<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler checks in every minute. Without it, holds wouldn't lapse,
 * reminders wouldn't go and unsettled payments wouldn't be chased, so it's
 * worth knowing at once when it stops.
 */
final class Heartbeat
{
    private const KEY = 'scheduler:heartbeat';

    /** How long without a check-in before it counts as stopped. */
    public const STALE_AFTER_MINUTES = 3;

    public static function beat(): void
    {
        Cache::forever(self::KEY, now()->utc()->toIso8601ZuluString());
    }

    public static function last(): ?CarbonImmutable
    {
        $at = Cache::get(self::KEY);

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    public static function healthy(): bool
    {
        $last = self::last();

        return $last !== null && $last->greaterThan(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }
}
