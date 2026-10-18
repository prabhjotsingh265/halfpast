<?php

namespace App\Events;

use App\Models\Business;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something on a business's calendar changed: a booking or a stretch of
 * time off. Open calendars showing any of those days reload them. Only the
 * days go over the socket, never booking details; the reload fetches those
 * with the viewer's own permissions.
 *
 * Queued, so a hiccup reaching the socket server never fails the request
 * that made the change, and sent only once the change is committed.
 */
class CalendarChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int $businessId,
        /** First local date affected, "2026-10-20". */
        public string $from,
        /** Last local date affected, included. */
        public string $to,
    ) {}

    /**
     * Tell open calendars about a stretch of time, given as real moments;
     * the days are worked out in the business's own time zone.
     */
    public static function between(int $businessId, DateTimeInterface $start, DateTimeInterface $end): void
    {
        $zone = (string) (Business::whereKey($businessId)->value('timezone') ?? 'UTC');
        $from = CarbonImmutable::instance($start)->setTimezone($zone)->toDateString();
        // The end is exclusive: time off ending at midnight doesn't touch the next day.
        $to = CarbonImmutable::instance($end)->subSecond()->setTimezone($zone)->toDateString();

        self::dispatch($businessId, $from, max($from, $to));
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("business.{$this->businessId}.calendar");
    }

    public function broadcastAs(): string
    {
        return 'calendar.changed';
    }

    /**
     * @return array{from: string, to: string}
     */
    public function broadcastWith(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}
