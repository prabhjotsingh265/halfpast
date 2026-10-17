<?php

namespace App\Support\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * One event as an iCalendar (.ics) file, the format every calendar app
 * imports (RFC 5545). Times are written in UTC, so the customer's calendar
 * shows them in whatever zone the customer is in.
 */
final readonly class IcsEvent
{
    public function __construct(
        /** Stays the same for the same booking, so re-importing updates it. */
        public string $uid,
        public DateTimeInterface $start,
        public DateTimeInterface $end,
        public string $summary,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $url = null,
    ) {}

    public function render(?DateTimeInterface $stamp = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Halfpast//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.self::text($this->uid),
            'DTSTAMP:'.self::utc($stamp ?? new DateTimeImmutable),
            'DTSTART:'.self::utc($this->start),
            'DTEND:'.self::utc($this->end),
            'SUMMARY:'.self::text($this->summary),
        ];

        if ($this->description !== null) {
            $lines[] = 'DESCRIPTION:'.self::text($this->description);
        }

        if ($this->location !== null) {
            $lines[] = 'LOCATION:'.self::text($this->location);
        }

        if ($this->url !== null) {
            $lines[] = 'URL:'.$this->url;
        }

        $lines = [...$lines, 'STATUS:CONFIRMED', 'END:VEVENT', 'END:VCALENDAR'];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }

    /** Backslashes, semicolons, commas and line breaks are escaped in text values. */
    private static function text(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }

    /**
     * Lines longer than 75 bytes are folded onto continuation lines that
     * start with a space, without splitting a multi-byte character.
     */
    private static function fold(string $line): string
    {
        $parts = [];
        $limit = 75;

        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $chunk;
            $line = substr($line, strlen($chunk));
            // Continuation lines start with a space, which counts towards the 75.
            $limit = 74;
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
