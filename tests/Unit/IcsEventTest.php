<?php

use App\Support\Calendar\IcsEvent;

function sampleEvent(array $override = []): IcsEvent
{
    $o = [
        'uid' => 'SZ6NBNKMDF@halfpast',
        'start' => new DateTimeImmutable('2026-10-18 10:30', new DateTimeZone('Asia/Kolkata')),
        'end' => new DateTimeImmutable('2026-10-18 10:45', new DateTimeZone('Asia/Kolkata')),
        'summary' => 'Eyebrow threading at Kesar Beauty Lounge',
        'description' => null,
        'location' => null,
        ...$override,
    ];

    return new IcsEvent($o['uid'], $o['start'], $o['end'], $o['summary'], $o['description'], $o['location']);
}

it('writes the event in UTC with CRLF line endings', function () {
    $ics = sampleEvent()->render(new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('UTC')));

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n")
        ->and($ics)->toContain("DTSTART:20261018T050000Z\r\n")
        ->and($ics)->toContain("DTEND:20261018T051500Z\r\n")
        ->and($ics)->toContain("DTSTAMP:20261017T120000Z\r\n")
        ->and($ics)->toContain("UID:SZ6NBNKMDF@halfpast\r\n")
        ->and($ics)->toEndWith("END:VEVENT\r\nEND:VCALENDAR\r\n");
});

it('escapes commas, semicolons, backslashes and line breaks', function () {
    $ics = sampleEvent(['summary' => 'Cut, colour; style \\ more', 'description' => "Line one\nLine two"])->render();

    expect($ics)->toContain('SUMMARY:Cut\, colour\; style \\\\ more')
        ->and($ics)->toContain('DESCRIPTION:Line one\nLine two');
});

it('folds long lines at 75 bytes without breaking a character', function () {
    $ics = sampleEvent(['description' => str_repeat('Rangoli ₹ ', 30)])->render();

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }

    // Unfolding (removing CRLF + space) gives the original text back.
    expect(str_replace("\r\n ", '', $ics))->toContain('DESCRIPTION:'.str_repeat('Rangoli ₹ ', 30));
});
