<?php

use App\Availability\Interval;
use App\Availability\IntervalSet;
use App\Availability\Slot;
use App\Availability\SlotFinder;
use App\Availability\SlotRequest;
use App\Availability\StaffCalendar;
use App\Availability\WeeklySchedule;

/*
 * 2 November 2026 is a Monday, and London is on GMT then, so local times
 * and UTC read the same unless a test says otherwise.
 */

function localMoment(string $time, string $zone = 'Europe/London'): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone($zone));
}

function slotRequest(array $override = []): SlotRequest
{
    $o = [
        'zone' => 'Europe/London',
        'from' => '2026-11-02',
        'to' => '2026-11-02',
        'duration' => 60,
        'buffer' => 0,
        'step' => 60,
        'earliest' => '2026-01-01 00:00',
        'latest' => '2027-12-31 00:00',
        ...$override,
    ];

    return new SlotRequest(
        $o['zone'], $o['from'], $o['to'], $o['duration'], $o['buffer'], $o['step'],
        localMoment($o['earliest'], $o['zone']), localMoment($o['latest'], $o['zone']),
    );
}

/**
 * @param  array<int, list<array{string, string}>>  $days  weekday => [["09:00", "17:00"], ...]
 */
function weekOf(array $days): WeeklySchedule
{
    $rows = [];

    foreach ($days as $weekday => $stretches) {
        foreach ($stretches as [$start, $end]) {
            $rows[] = ['weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end];
        }
    }

    return WeeklySchedule::fromRows($rows);
}

/** @param list<array{string, string}> $blocks */
function blocksOf(array $blocks, string $zone = 'Europe/London'): IntervalSet
{
    return new IntervalSet(array_map(
        fn (array $block) => Interval::between(localMoment($block[0], $zone), localMoment($block[1], $zone)),
        $blocks,
    ));
}

function calendarFor(int $id, array $days, array $off = [], array $busy = [], string $zone = 'Europe/London'): StaffCalendar
{
    return new StaffCalendar($id, weekOf($days), blocksOf($off, $zone), blocksOf($busy, $zone));
}

/**
 * @param  list<Slot>  $slots
 * @return list<string>
 */
function clockTimes(array $slots, string $format = 'H:i', string $zone = 'Europe/London'): array
{
    return array_map(fn (Slot $slot) => $slot->start->setTimezone(new DateTimeZone($zone))->format($format), $slots);
}

const MONDAY_9_TO_5 = [1 => [['09:00', '17:00']]];

it('offers each hour of a working day', function () {
    $slots = (new SlotFinder)->find(slotRequest(), [calendarFor(1, MONDAY_9_TO_5)]);

    expect(clockTimes($slots))->toBe(['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'])
        ->and($slots[0]->end->format('H:i'))->toBe('10:00')
        ->and($slots[0]->staffIds)->toBe([1]);
});

it('works out slots from the hours, the step and the length', function (array $days, array $override, array $expected) {
    $slots = (new SlotFinder)->find(slotRequest($override), [calendarFor(1, $days)]);

    expect(clockTimes($slots))->toBe($expected);
})->with([
    'a lunch break is a gap' => [
        [1 => [['09:00', '13:00'], ['14:00', '17:00']]], [],
        ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00'],
    ],
    'the last slot has to finish by closing' => [
        [1 => [['09:00', '10:00']]], ['duration' => 45, 'step' => 30],
        ['09:00'],
    ],
    'slots start from when the stretch starts' => [
        [1 => [['09:10', '10:30']]], ['duration' => 30, 'step' => 15],
        ['09:10', '09:25', '09:40', '09:55'],
    ],
    'stretches that touch join up' => [
        [1 => [['09:00', '10:00'], ['10:00', '11:00']]], ['duration' => 90, 'step' => 30],
        ['09:00', '09:30'],
    ],
    'the free time after may run past closing' => [
        [1 => [['09:00', '10:00']]], ['duration' => 30, 'buffer' => 15, 'step' => 30],
        ['09:00', '09:30'],
    ],
    'working until midnight' => [
        [1 => [['21:00', '24:00']]], [],
        ['21:00', '22:00', '23:00'],
    ],
    'a day off has nothing' => [
        [2 => [['09:00', '17:00']]], [],
        [],
    ],
]);

it('keeps clear of bookings, including the free time after a new one', function () {
    // Already booked 10:00–10:40 (a 30 minute visit and 10 minutes to tidy up).
    $priya = calendarFor(1, [1 => [['09:00', '12:00']]], busy: [['2026-11-02 10:00', '2026-11-02 10:40']]);

    $slots = (new SlotFinder)->find(slotRequest(['duration' => 30, 'buffer' => 10, 'step' => 15]), [$priya]);

    // 09:15 would run, with its tidy-up, to 09:55; 09:30 would run into 10:00.
    expect(clockTimes($slots))->toBe(['09:00', '09:15', '10:45', '11:00', '11:15', '11:30']);
});

it('leaves out time off and closures', function () {
    $priya = calendarFor(1, MONDAY_9_TO_5, off: [['2026-11-02 12:00', '2026-11-02 15:00']]);
    $closed = blocksOf([['2026-11-02 16:00', '2026-11-03 00:00']]);

    $slots = (new SlotFinder)->find(slotRequest(), [$priya], $closed);

    expect(clockTimes($slots))->toBe(['09:00', '10:00', '11:00', '15:00']);
});

it('respects the minimum notice and how far ahead bookings go', function () {
    $slots = (new SlotFinder)->find(slotRequest([
        'step' => 30,
        // Now is 09:20 with two hours' notice, and nothing after 14:00.
        'earliest' => '2026-11-02 11:20',
        'latest' => '2026-11-02 14:00',
    ]), [calendarFor(1, MONDAY_9_TO_5)]);

    expect(clockTimes($slots))->toBe(['11:30', '12:00', '12:30', '13:00', '13:30', '14:00']);
});

it('lists everyone free at each time', function () {
    $priya = calendarFor(7, [1 => [['09:00', '12:00']]]);
    $sam = calendarFor(3, [1 => [['10:00', '13:00']]], busy: [['2026-11-02 11:00', '2026-11-02 12:00']]);

    $slots = (new SlotFinder)->find(slotRequest(), [$priya, $sam]);

    expect(array_map(fn (Slot $slot) => [$slot->start->format('H:i'), $slot->staffIds], $slots))->toBe([
        ['09:00', [7]],
        ['10:00', [3, 7]],
        ['11:00', [7]],
        ['12:00', [3]],
    ]);
});

it('runs across several days in order', function () {
    $slots = (new SlotFinder)->find(
        slotRequest(['from' => '2026-11-01', 'to' => '2026-11-04', 'duration' => 120, 'step' => 240]),
        [calendarFor(1, [1 => [['09:00', '13:00']], 3 => [['14:00', '18:00']]])],
    );

    expect(clockTimes($slots, 'D H:i'))->toBe(['Mon 09:00', 'Wed 14:00']);
});

it('reads hours in the business time zone', function () {
    $slots = (new SlotFinder)->find(
        slotRequest(['zone' => 'Asia/Kolkata', 'step' => 240]),
        [calendarFor(1, MONDAY_9_TO_5, zone: 'Asia/Kolkata')],
    );

    expect(clockTimes($slots, 'H:i', 'UTC'))->toBe(['03:30', '07:30'])
        ->and(clockTimes($slots, 'H:i', 'Asia/Kolkata'))->toBe(['09:00', '13:00']);
});

describe('when the clocks change', function () {
    it('keeps 9 am at 9 am on the clock either side of the change', function () {
        $everyDay = array_fill(1, 7, [['09:00', '10:00']]);

        $slots = (new SlotFinder)->find(
            slotRequest(['from' => '2026-10-24', 'to' => '2026-10-26']),
            [calendarFor(1, $everyDay)],
        );

        expect(clockTimes($slots, 'D H:i'))->toBe(['Sat 09:00', 'Sun 09:00', 'Mon 09:00'])
            // British Summer Time ends early on Sunday 25 October.
            ->and(clockTimes($slots, 'D H:i', 'UTC'))->toBe(['Sat 08:00', 'Sun 09:00', 'Mon 09:00']);
    });

    it('fits three hours into the night the clocks go forward', function () {
        $slots = (new SlotFinder)->find(
            slotRequest(['from' => '2027-03-28', 'to' => '2027-03-28']),
            [calendarFor(1, [7 => [['00:00', '04:00']]])],
        );

        // 01:00 to 02:00 never happens, so 00:00–04:00 is three real hours.
        expect(clockTimes($slots))->toBe(['00:00', '02:00', '03:00'])
            ->and(clockTimes($slots, 'H:i', 'UTC'))->toBe(['00:00', '01:00', '02:00']);
    });

    it('fits five hours into the night the clocks go back', function () {
        $slots = (new SlotFinder)->find(
            slotRequest(['from' => '2026-10-25', 'to' => '2026-10-25']),
            [calendarFor(1, [7 => [['00:00', '04:00']]])],
        );

        // 01:00 to 02:00 happens twice, so the night has five real hours.
        expect(clockTimes($slots, 'H:i T'))->toBe(['00:00 BST', '01:00 BST', '01:00 GMT', '02:00 GMT', '03:00 GMT'])
            ->and(count(array_unique(array_map(fn (Slot $s) => $s->start->getTimestamp(), $slots))))->toBe(5);
    });

    it('moves a time the clocks skip on to the first one that exists', function () {
        $moment = SlotFinder::localTime('2027-03-28', 90, new DateTimeZone('Europe/London'));

        expect($moment->format('H:i T'))->toBe('02:30 BST')
            ->and($moment->setTimezone(new DateTimeZone('UTC'))->format('H:i'))->toBe('01:30');
    });

    it('treats a whole day off as the whole day, however long it is', function () {
        $everyDay = array_fill(1, 7, [['00:00', '24:00']]);
        // Time off for Sunday 25 October, midnight to midnight: 25 real hours.
        $priya = calendarFor(1, $everyDay, off: [['2026-10-25 00:00', '2026-10-26 00:00']]);

        $slots = (new SlotFinder)->find(
            slotRequest(['from' => '2026-10-24', 'to' => '2026-10-26', 'step' => 720, 'duration' => 60]),
            [$priya],
        );

        expect(clockTimes($slots, 'D H:i'))->toBe(['Sat 00:00', 'Sat 12:00', 'Mon 00:00', 'Mon 12:00']);
    });
});

describe('interval sets', function () {
    it('merges intervals that overlap or touch', function () {
        $set = new IntervalSet([new Interval(30, 40), new Interval(0, 10), new Interval(10, 20), new Interval(35, 50)]);

        expect(array_map(fn (Interval $i) => [$i->start, $i->end], $set->intervals))->toBe([[0, 20], [30, 50]]);
    });

    it('takes one set out of another', function () {
        $working = new IntervalSet([new Interval(0, 100)]);
        $off = new IntervalSet([new Interval(10, 20), new Interval(50, 60), new Interval(90, 120)]);

        expect(array_map(fn (Interval $i) => [$i->start, $i->end], $working->subtract($off)->intervals))
            ->toBe([[0, 10], [20, 50], [60, 90]]);
    });

    it('lets intervals touch without overlapping', function () {
        expect((new Interval(0, 10))->overlaps(new Interval(10, 20)))->toBeFalse()
            ->and((new Interval(0, 10))->overlaps(new Interval(9, 20)))->toBeTrue();
    });
});

it('refuses hours that are not a stretch of one day', function () {
    new WeeklySchedule([1 => [[600, 540]]]);
})->throws(InvalidArgumentException::class);
