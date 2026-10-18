<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'UTC'));
    $this->owner = User::factory()->create(['password' => 'secret-password']);
    $this->business = Business::factory()->ownedBy($this->owner)->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
    app(CurrentBusiness::class)->set($this->business);
    $this->staff = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
});

/** The export, opened: file name => contents. */
function exported(): array
{
    $response = test()->actingAs(test()->owner)->get(route('business.data.export'))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());

    $files = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }

    $zip->close();

    return $files;
}

function rows(string $csv): array
{
    $lines = array_map(fn (string $line) => str_getcsv($line, escape: ''), array_filter(explode("\n", trim($csv))));
    $header = array_shift($lines);

    return array_map(fn (array $line) => array_combine($header, $line), $lines);
}

it('downloads everything as spreadsheets, in the business\'s own time', function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com']);
    Customer::factory()->for($this->business)->create(['name' => 'Removed customer', 'forgotten_at' => now()]);
    $booking = Booking::factory()->for($this->business)->at('2026-10-28 04:30', '2026-10-28 05:30')->create([
        'staff_id' => $this->staff->id, 'customer_id' => $ananya->id, 'service_name' => 'Colour', 'price' => 150000, 'deposit' => 30000, 'currency' => 'INR',
    ]);
    Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $this->business->id, 'amount' => 30000, 'currency' => 'INR']);
    Service::factory()->for($this->business)->create(['name' => 'Colour', 'price' => 150000]);

    $response = $this->actingAs($this->owner)->get(route('business.data.export'));
    expect($response->headers->get('content-disposition'))->toContain('glow-studio-halfpast-2026-10-27.zip');

    $files = exported();
    expect(array_keys($files))->toEqualCanonicalizing(['bookings.csv', 'customers.csv', 'payments.csv', 'refunds.csv', 'services.csv', 'team.csv', 'time-off.csv', 'business.json', 'README.txt']);

    $bookings = rows($files['bookings.csv']);
    expect($bookings)->toHaveCount(1)
        ->and($bookings[0])->toMatchArray([
            'reference' => $booking->displayCode(),
            'service' => 'Colour',
            'with' => 'Priya',
            'customer' => 'Ananya Iyer',
            // 04:30 UTC is 10:00 in Kolkata.
            'starts_local' => '2026-10-28 10:00',
            'starts_utc' => '2026-10-28T04:30:00Z',
            'price' => '1500.00',
            'deposit' => '300.00',
        ])
        ->and(array_column(rows($files['customers.csv']), 'name'))->toBe(['Ananya Iyer'])
        ->and(rows($files['payments.csv'])[0]['amount'])->toBe('300.00')
        ->and(json_decode($files['business.json'], true)['timezone'])->toBe('Asia/Kolkata')
        ->and($files['README.txt'])->toContain('Asia/Kolkata');
});

it("stops a customer's name running as a spreadsheet formula", function () {
    Customer::factory()->for($this->business)->create(['name' => '=HYPERLINK("http://evil.example","Click")', 'notes' => '-5 minutes late is fine']);

    $customer = rows(exported()['customers.csv'])[0];

    expect($customer['name'])->toStartWith("'=HYPERLINK")
        ->and($customer['notes'])->toBe("'-5 minutes late is fine");
});

it("only ever holds this business's data, and only owners can download it", function () {
    $other = Business::factory()->create();
    app(CurrentBusiness::class)->set($other);
    Booking::factory()->for($other)->at('2026-10-28 04:30', '2026-10-28 05:30')->create(['service_name' => 'Not ours']);
    app(CurrentBusiness::class)->set($this->business);

    expect(exported()['bookings.csv'])->not->toContain('Not ours');

    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);
    $this->actingAs($staffUser)->get(route('business.data.export'))->assertForbidden();
});

it('deletes the business for good, once the name and password are right', function () {
    $past = Booking::factory()->for($this->business)->at('2026-10-20 04:30', '2026-10-20 05:30')->create(['status' => BookingStatus::Completed]);

    $this->actingAs($this->owner)->withSession([SetCurrentBusiness::SESSION_KEY => $this->business->id]);

    $this->delete(route('business.destroy'), ['name' => 'Glow', 'password' => 'secret-password'])->assertSessionHasErrors('name');
    $this->delete(route('business.destroy'), ['name' => 'glow studio', 'password' => 'wrong'])->assertSessionHasErrors(['password' => "That isn't your password."]);
    expect($this->business->fresh())->not->toBeNull();

    $this->delete(route('business.destroy'), ['name' => ' glow studio ', 'password' => 'secret-password'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing(SetCurrentBusiness::SESSION_KEY)
        ->assertInertiaFlash('toast.message', 'Glow Studio is deleted, with everything in it.');

    expect(Business::find($this->business->id))->toBeNull()
        ->and(Booking::withoutGlobalScopes()->find($past->id))->toBeNull()
        ->and($this->owner->fresh())->not->toBeNull();
});

it('keeps a business that still has bookings to come, or is a demo', function () {
    Booking::factory()->for($this->business)->at('2026-11-02 04:30', '2026-11-02 05:30')->create(['status' => BookingStatus::Confirmed]);

    $this->actingAs($this->owner)
        ->delete(route('business.destroy'), ['name' => 'Glow Studio', 'password' => 'secret-password'])
        ->assertSessionHasErrors(['name' => 'There is 1 booking still to come. Cancel it from the calendar first, so customers are told and get their deposits back.']);

    $demoOwner = User::factory()->create(['password' => 'secret-password']);
    $demo = Business::factory()->ownedBy($demoOwner)->create(['name' => 'Demo Salon']);
    $demo->forceFill(['is_demo' => true])->save();

    $this->actingAs($demoOwner)->withSession([SetCurrentBusiness::SESSION_KEY => $demo->id])
        ->delete(route('business.destroy'), ['name' => 'Demo Salon', 'password' => 'secret-password'])
        ->assertSessionHasErrors('name');

    expect($this->business->fresh())->not->toBeNull()
        ->and($demo->fresh())->not->toBeNull();
});

it('shows what there is to download and whether the business can go', function () {
    Booking::factory()->for($this->business)->at('2026-11-02 04:30', '2026-11-02 05:30')->create(['status' => BookingStatus::Confirmed]);

    $this->actingAs($this->owner)
        ->get(route('business.data.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/Data')
            ->where('name', 'Glow Studio')
            ->where('isDemo', false)
            ->where('counts.bookings', 1)
            ->where('upcoming', 1));
});

it("won't delete the account of a business's only owner", function () {
    $this->actingAs($this->owner)
        ->delete(route('profile.destroy'), ['password' => 'secret-password'])
        ->assertSessionHasErrors(['password' => 'You still run Glow Studio. Delete it in Business settings, under Your data, first.']);
    expect($this->owner->fresh())->not->toBeNull();

    // With someone else to run it, they can go.
    $partner = User::factory()->create();
    $this->business->addMember($partner, BusinessRole::Owner);

    $this->actingAs($this->owner)->delete(route('profile.destroy'), ['password' => 'secret-password'])->assertRedirect('/');
    expect($this->owner->fresh())->toBeNull();
});
