<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London']);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
});

function timeOffForm(array $override = []): array
{
    return [
        'staff_id' => null,
        'all_day' => true,
        'starts_on' => '2026-12-24',
        'ends_on' => '2026-12-26',
        'reason' => 'Christmas',
        ...$override,
    ];
}

it('closes the whole business for full days', function () {
    $this->actingAs($this->owner)
        ->post(route('time-off.store'), timeOffForm())
        ->assertRedirect(route('time-off.index'));

    $entry = TimeOff::sole();

    expect($entry->business_id)->toBe($this->business->id)
        ->and($entry->staff_id)->toBeNull()
        ->and($entry->starts_at->toIso8601String())->toBe('2026-12-24T00:00:00+00:00')
        ->and($entry->ends_at->toIso8601String())->toBe('2026-12-27T00:00:00+00:00');
});

it('stores local times as the real moments in UTC', function () {
    $this->business->update(['timezone' => 'Asia/Kolkata']);

    $this->actingAs($this->owner)
        ->post(route('time-off.store'), timeOffForm([
            'staff_id' => $this->priya->id,
            'all_day' => false,
            'starts_on' => '2026-11-03',
            'ends_on' => '2026-11-03',
            'starts_time' => '14:00',
            'ends_time' => '17:30',
            'reason' => 'Dentist',
        ]))
        ->assertRedirect(route('time-off.index'));

    $entry = TimeOff::sole();

    expect($entry->staff_id)->toBe($this->priya->id)
        ->and($entry->starts_at->toIso8601String())->toBe('2026-11-03T08:30:00+00:00')
        ->and($entry->ends_at->toIso8601String())->toBe('2026-11-03T12:00:00+00:00');
});

it('keeps a whole day whole when the clocks change', function (string $day, int $hours) {
    $this->actingAs($this->owner)
        ->post(route('time-off.store'), timeOffForm(['starts_on' => $day, 'ends_on' => $day]));

    $entry = TimeOff::sole();

    expect((int) $entry->starts_at->diffInHours($entry->ends_at))->toBe($hours)
        ->and($entry->starts_at->setTimezone('Europe/London')->format('Y-m-d H:i'))->toBe("{$day} 00:00");
})->with([
    'clocks go back in October' => ['2026-10-25', 25],
    'clocks go forward in March' => ['2027-03-28', 23],
]);

it('shows what is coming up in the business time zone', function () {
    TimeOff::factory()->for($this->business)->create([
        'staff_id' => $this->priya->id,
        'starts_at' => CarbonImmutable::parse('2026-12-24 00:00', 'Europe/London'),
        'ends_at' => CarbonImmutable::parse('2026-12-27 00:00', 'Europe/London'),
        'reason' => 'Christmas',
    ]);
    TimeOff::factory()->for($this->business)->create([
        'starts_at' => CarbonImmutable::parse('2026-11-03 14:00', 'Europe/London'),
        'ends_at' => CarbonImmutable::parse('2026-11-03 17:30', 'Europe/London'),
    ]);
    // Already over, so it's left off the list.
    TimeOff::factory()->for($this->business)->create([
        'starts_at' => CarbonImmutable::parse('2026-10-01 00:00', 'Europe/London'),
        'ends_at' => CarbonImmutable::parse('2026-10-02 00:00', 'Europe/London'),
    ]);

    $this->actingAs($this->owner)
        ->get(route('time-off.index', ['staff' => $this->priya->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-off/Index')
            ->where('today', '2026-10-16')
            ->where('timezone', 'Europe/London')
            ->where('preselectedStaffId', $this->priya->id)
            ->has('staff', 1)
            ->has('entries', 2)
            ->where('entries.0.all_day', false)
            ->where('entries.0.starts_on', '2026-11-03')
            ->where('entries.0.starts_time', '14:00')
            ->where('entries.0.ends_time', '17:30')
            ->where('entries.0.staff_name', null)
            ->where('entries.1.all_day', true)
            ->where('entries.1.starts_on', '2026-12-24')
            ->where('entries.1.ends_on', '2026-12-26')
            ->where('entries.1.staff_name', 'Priya'));
});

it('removes time off', function () {
    $entry = TimeOff::factory()->for($this->business)->create();

    $this->actingAs($this->owner)
        ->delete(route('time-off.destroy', $entry))
        ->assertRedirect(route('time-off.index'));

    expect(TimeOff::count())->toBe(0);
});

it('rejects time off that does not add up', function (array $override, string $field) {
    $this->actingAs($this->owner)
        ->post(route('time-off.store'), timeOffForm($override))
        ->assertSessionHasErrors($field);

    expect(TimeOff::count())->toBe(0);
})->with([
    'last day before the first' => [['ends_on' => '2026-12-20'], 'ends_on'],
    'not a date' => [['starts_on' => '24/12/2026'], 'starts_on'],
    'ends before it starts' => [['all_day' => false, 'ends_on' => '2026-12-24', 'starts_time' => '15:00', 'ends_time' => '11:00'], 'ends_time'],
    'missing a time' => [['all_day' => false, 'starts_time' => '09:00'], 'ends_time'],
    'more than a year' => [['ends_on' => '2028-01-10'], 'ends_on'],
]);

it("keeps other businesses' time off and staff out of reach", function () {
    $other = Business::factory()->create();
    $theirEntry = TimeOff::factory()->for($other)->create();
    $theirStaff = Staff::factory()->for($other)->create();

    $this->actingAs($this->owner);

    $this->delete(route('time-off.destroy', $theirEntry))->assertNotFound();
    $this->post(route('time-off.store'), timeOffForm(['staff_id' => $theirStaff->id]))->assertSessionHasErrors('staff_id');
    $this->get(route('time-off.index'))->assertInertia(fn (Assert $page) => $page->has('entries', 0));

    expect(TimeOff::withoutGlobalScopes()->whereKey($theirEntry->id)->exists())->toBeTrue();
});

it('is only for owners', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);

    $this->actingAs($staffUser)->get(route('time-off.index'))->assertForbidden();
});
