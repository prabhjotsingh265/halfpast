<?php

use App\Demo\ResetDemo;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentStatus;
use App\Http\Middleware\ProtectDemoAccounts;
use App\Http\Middleware\SetCurrentBusiness;
use App\Listeners\SkipUndeliverableAddresses;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Payments\Gateways;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mime\Email;

/*
 * Tuesday 27 October 2026, 08:00 UTC. The demo is built two days either side
 * of today, to keep the tests quick.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 08:00', 'UTC'));
    config(['demo.enabled' => true]);
});

function buildDemo(): array
{
    return app(ResetDemo::class)->handle(daysBefore: 2, daysAfter: 2);
}

function demoBusiness(string $slug): Business
{
    return Business::where('slug', $slug)->sole();
}

it('builds the three demo businesses with their logins', function () {
    expect(buildDemo())->toBe(['built' => ['kesar-beauty-lounge', 'fade-and-co', 'stillwater-physio'], 'skipped' => []]);

    expect(Business::where('is_demo', true)->pluck('currency', 'slug')->all())->toEqual([
        'kesar-beauty-lounge' => 'INR',
        'fade-and-co' => 'GBP',
        'stillwater-physio' => 'USD',
    ])
        ->and(User::where('is_demo', true)->pluck('email')->sort()->values()->all())->toBe([
            'barber@halfpast.test', 'clinic@halfpast.test', 'demo@halfpast.test', 'staff@halfpast.test',
        ])
        ->and(User::where('email', 'staff@halfpast.test')->sole()->roleIn(demoBusiness('kesar-beauty-lounge')))->toBe(BusinessRole::Staff)
        ->and(Staff::withoutGlobalScopes()->where('name', 'Rohan Mehta')->sole()->user?->email)->toBe('staff@halfpast.test');
});

it('fills each diary with history, today and what is coming, all of it adding up', function () {
    buildDemo();

    foreach (Business::where('is_demo', true)->get() as $business) {
        $bookings = Booking::withoutGlobalScopes()->where('business_id', $business->id)->get();

        expect($bookings->where('starts_at', '<', now())->count())->toBeGreaterThan(0)
            ->and($bookings->where('starts_at', '>', now())->count())->toBeGreaterThan(0)
            ->and($bookings->where('status', BookingStatus::Completed)->count())->toBeGreaterThan(0)
            // Nothing in the future is finished yet.
            ->and($bookings->filter(fn (Booking $b) => $b->starts_at->isFuture() && in_array($b->status, [BookingStatus::Completed, BookingStatus::NoShow], true)))->toBeEmpty();

        // Every deposit taken is in the ledger, and every refund comes off it.
        $payments = Payment::withoutGlobalScopes()->where('business_id', $business->id)->get();
        $ledger = LedgerEntry::withoutGlobalScopes()->where('business_id', $business->id)->get();

        expect($payments->every(fn (Payment $p) => $p->gateway === 'test'))->toBeTrue()
            ->and((int) $ledger->where('type', 'deposit')->sum('amount'))->toBe((int) $payments->sum('amount'))
            ->and((int) -$ledger->where('type', 'refund')->sum('amount'))->toBe((int) $payments->where('status', PaymentStatus::Refunded)->sum('amount'));
    }
});

it('builds the same diary for the same day', function () {
    buildDemo();
    $first = Booking::withoutGlobalScopes()->orderBy('starts_at')->get()->map(fn (Booking $b) => [$b->service_name, $b->starts_at->toIso8601String(), $b->status->value])->all();

    buildDemo();
    $second = Booking::withoutGlobalScopes()->orderBy('starts_at')->get()->map(fn (Booking $b) => [$b->service_name, $b->starts_at->toIso8601String(), $b->status->value])->all();

    expect($second)->toBe($first);
});

it('rebuilds without ever touching a real business or person', function () {
    $realOwner = User::factory()->create(['email' => 'real@example.com']);
    $real = Business::factory()->ownedBy($realOwner)->create(['slug' => 'real-salon']);
    app(CurrentBusiness::class)->set($real);
    $realBooking = Booking::factory()->for($real)->at('2026-10-28 09:00', '2026-10-28 10:00')->create();
    app(CurrentBusiness::class)->set(null);

    buildDemo();
    buildDemo();

    expect(Business::where('is_demo', true)->count())->toBe(3)
        ->and(User::where('is_demo', true)->count())->toBe(4)
        ->and($real->fresh())->not->toBeNull()
        ->and($realOwner->fresh())->not->toBeNull()
        ->and($realBooking->fresh())->not->toBeNull();
});

it('skips a demo whose address or login a real account has taken', function () {
    Business::factory()->create(['slug' => 'fade-and-co']);
    User::factory()->create(['email' => 'clinic@halfpast.test']);

    expect(buildDemo())->toBe(['built' => ['kesar-beauty-lounge'], 'skipped' => ['fade-and-co', 'stillwater-physio']])
        ->and(Business::where('slug', 'fade-and-co')->sole()->is_demo)->toBeFalse();
});

it('rebuilds from the command, every night, only while demo mode is on', function () {
    config(['demo.enabled' => false]);
    $this->artisan('demo:reset')->assertFailed();
    expect(Business::count())->toBe(0);

    config(['demo.enabled' => true, 'demo.days_before' => 1, 'demo.days_after' => 1]);
    $this->artisan('demo:reset')->assertSuccessful();
    expect(Business::where('is_demo', true)->count())->toBe(3);

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'demo:reset'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *')
        ->and($event->timezone)->toBe('UTC');
});

it('logs straight into a demo business, as the owner or as staff', function () {
    buildDemo();
    $kesar = demoBusiness('kesar-beauty-lounge');

    $this->post(route('demo.login', ['slug' => 'kesar-beauty-lounge']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(SetCurrentBusiness::SESSION_KEY, $kesar->id);
    $this->assertAuthenticatedAs(User::where('email', 'demo@halfpast.test')->sole());

    auth()->logout();
    $this->post(route('demo.login', ['slug' => 'kesar-beauty-lounge']), ['as' => 'staff'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs(User::where('email', 'staff@halfpast.test')->sole());

    // The barber has no staff login, so that's not on offer.
    auth()->logout();
    $this->post(route('demo.login', ['slug' => 'fade-and-co']), ['as' => 'staff'])->assertNotFound();
});

it('offers no demo logins when demo mode is off, or for real businesses', function () {
    buildDemo();
    Business::factory()->create(['slug' => 'real-salon']);

    $this->post(route('demo.login', ['slug' => 'real-salon']))->assertNotFound();

    config(['demo.enabled' => false]);
    $this->post(route('demo.login', ['slug' => 'kesar-beauty-lounge']))->assertNotFound();
    $this->assertGuest();
});

it('lists the demos on the login page while demo mode is on', function () {
    buildDemo();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->has('demos', 3)
        ->where('demos.0.slug', 'kesar-beauty-lounge')
        ->where('demos.0.summary', 'A salon in Kolkata, in rupees')
        ->where('demos.0.logins', [['as' => 'owner', 'label' => 'Priya, the owner'], ['as' => 'staff', 'label' => 'Rohan, on the team']])
        ->where('demos.1.logins', [['as' => 'owner', 'label' => 'Tom, the owner']]));

    config(['demo.enabled' => false]);
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demos', []));
});

it("keeps demo logins' sign-in details as they are", function () {
    buildDemo();
    $demo = User::where('email', 'demo@halfpast.test')->sole();

    $this->actingAs($demo);
    $this->patch(route('profile.update'), ['name' => 'Hacked', 'email' => 'hacked@example.com'])
        ->assertInertiaFlash('toast.message', ProtectDemoAccounts::MESSAGE);
    $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']);
    $this->delete(route('profile.destroy'), ['password' => 'password']);
    $this->post(route('onboarding.store'), ['name' => 'Side hustle', 'slug' => 'side-hustle', 'timezone' => 'UTC', 'currency' => 'GBP']);

    $demo->refresh();
    expect($demo->name)->toBe('Priya Sharma')
        ->and($demo->email)->toBe('demo@halfpast.test')
        ->and(password_verify('password', $demo->password))->toBeTrue()
        ->and(Business::where('slug', 'side-hustle')->exists())->toBeFalse();

    // Real accounts are unaffected.
    $real = User::factory()->create();
    $this->actingAs($real)->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $real->email])->assertSessionHasNoErrors();
    expect($real->fresh()->name)->toBe('Renamed');
});

it("doesn't send emails the business would send, from a demo business", function () {
    buildDemo();
    $kesar = demoBusiness('kesar-beauty-lounge');
    app(CurrentBusiness::class)->set($kesar);
    $service = Service::where('name', 'Beard trim')->sole();
    $rohan = Staff::where('name', 'Rohan Mehta')->sole();
    $upcoming = Booking::where('status', BookingStatus::Confirmed)->whereNotNull('customer_id')->where('starts_at', '>', now()->addDay())->first();
    app(CurrentBusiness::class)->set(null);

    $this->post(route('demo.login', ['slug' => 'kesar-beauty-lounge']));
    $this->post(route('bookings.store'), [
        'service' => $service->id, 'staff' => $rohan->id, 'date' => '2026-10-29', 'time' => '20:00',
        'name' => 'Someone Real', 'email' => 'someone.real@gmail.com', 'tell_customer' => true,
    ])->assertSessionHasNoErrors();

    if ($upcoming) {
        $this->post(route('bookings.cancel', $upcoming), ['refund' => true, 'tell_customer' => true]);
    }

    Mail::assertNotQueued(BookingConfirmedMail::class);
    Mail::assertNotQueued(BookingCancelledMail::class);
});

it('lets demo businesses take test payments, even in production', function () {
    buildDemo();
    $gateways = app(Gateways::class);
    $kesar = demoBusiness('kesar-beauty-lounge');
    $real = Business::factory()->create(['payment_gateways' => ['test'], 'currency' => 'GBP']);

    $this->app['env'] = 'production';

    try {
        expect(array_keys($gateways->for($kesar)))->toContain('test')
            ->and(array_keys($gateways->for($real)))->not->toContain('test');

        config(['demo.enabled' => false]);
        expect(array_keys($gateways->for($kesar)))->not->toContain('test');
    } finally {
        $this->app['env'] = 'testing';
    }
});

it('marks test payments as test mode on the payment step', function () {
    $gateways = app(Gateways::class);

    expect($gateways->get('test')->isTestMode())->toBeTrue();

    config(['payments.gateways.stripe.secret' => 'sk_test_abc', 'payments.gateways.razorpay.key_id' => 'rzp_test_abc']);
    expect($gateways->get('stripe')->isTestMode())->toBeTrue()
        ->and($gateways->get('razorpay')->isTestMode())->toBeTrue();

    config(['payments.gateways.stripe.secret' => 'sk_live_abc', 'payments.gateways.razorpay.key_id' => 'rzp_live_abc']);
    expect($gateways->get('stripe')->isTestMode())->toBeFalse()
        ->and($gateways->get('razorpay')->isTestMode())->toBeFalse();
});

it('shares whether the business is a demo with every page', function () {
    buildDemo();

    $this->post(route('demo.login', ['slug' => 'fade-and-co']));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('business.is_demo', true)
        ->where('demoResetAt', '03:00'));
});

it('never mails addresses that cannot receive mail, once switched on', function () {
    $listener = new SkipUndeliverableAddresses;
    $send = fn (string ...$to) => $listener->handle(new MessageSending((new Email)->from('hi@halfpast.app')->to(...$to)->text('Hi')));

    config(['mail.skip_reserved_domains' => true]);
    expect($send('ananya@example.com'))->toBeFalse()
        ->and($send('demo@halfpast.test'))->toBeFalse()
        ->and($send('someone@gmail.com'))->toBeTrue();

    $mixed = (new Email)->from('hi@halfpast.app')->to('ananya@example.com', 'someone@gmail.com')->text('Hi');
    expect($listener->handle(new MessageSending($mixed)))->toBeTrue()
        ->and(array_map(fn ($a) => $a->getAddress(), $mixed->getTo()))->toBe(['someone@gmail.com']);

    config(['mail.skip_reserved_domains' => false]);
    expect($send('ananya@example.com'))->toBeTrue();
});
