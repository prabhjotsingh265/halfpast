<?php

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\Booking;
use App\Models\Business;
use App\Models\FailedJob;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->admin->forceFill(['is_admin' => true])->save();
});

function failedJob(string $job = 'App\\Jobs\\SendRefund'): FailedJob
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => $job, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
        'exception' => "RuntimeException: The gateway was down.\n#0 /app/vendor/...",
        'failed_at' => now(),
    ]);

    return FailedJob::latest('id')->firstOrFail();
}

it('keeps everyone but platform admins out', function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();
    $this->actingAs($owner)->get('/admin')->assertForbidden();

    // Shared demo logins never get in, even if marked as admin.
    $demo = User::factory()->create();
    $demo->forceFill(['is_demo' => true, 'is_admin' => true])->save();
    $this->actingAs($demo)->get('/admin')->assertForbidden();

    $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Failed jobs');
});

it('opens every section, and a record in each', function () {
    $business = Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio']);
    $booking = Booking::factory()->for($business)->at('2026-10-27 09:00', '2026-10-27 10:00')->create(['service_name' => 'Colour']);
    $payment = Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $business->id, 'amount' => 2000, 'currency' => 'GBP']);
    $event = PaymentEvent::create(['gateway' => 'stripe', 'event_id' => 'evt_123', 'type' => 'checkout.session.completed', 'payload' => ['id' => 'evt_123']]);
    $job = failedJob();

    $this->actingAs($this->admin);

    foreach (['businesses', 'bookings', 'payments', 'webhook-events', 'failed-jobs'] as $section) {
        $this->get("/admin/{$section}")->assertOk();
    }

    $this->get('/admin/businesses/glow-studio')->assertOk()->assertSee('Glow Studio');
    $this->get("/admin/bookings/{$booking->code}")->assertOk()->assertSee('Colour');
    $this->get("/admin/payments/{$payment->id}")->assertOk()->assertSee('£20.00');
    $this->get("/admin/webhook-events/{$event->id}")->assertOk()->assertSee('evt_123');
    $this->get("/admin/failed-jobs/{$job->id}")->assertOk()->assertSee('The gateway was down.');
});

it('looks across every business, even for an admin who works at one', function () {
    $own = Business::factory()->ownedBy($this->admin)->create(['name' => 'Mine']);
    $other = Business::factory()->create(['name' => 'Someone else']);
    $mine = Booking::factory()->for($own)->at('2026-10-27 09:00', '2026-10-27 10:00')->create();
    $theirs = Booking::factory()->for($other)->at('2026-10-27 09:00', '2026-10-27 10:00')->create();

    $this->actingAs($this->admin)->withSession([SetCurrentBusiness::SESSION_KEY => $own->id]);

    Livewire::test(ListBusinesses::class)->assertCanSeeTableRecords([$own, $other]);
    Livewire::test(ListBookings::class)->assertCanSeeTableRecords([$mine, $theirs]);

    // Livewire's own requests skip the middleware that would narrow them to one business.
    expect(Route::getRoutes()->getByName('livewire.update')->excludedMiddleware())->toContain(SetCurrentBusiness::class);
});

it('tries a failed job again, or clears it', function () {
    Queue::fake();
    $retry = failedJob();
    $clear = failedJob('App\\Jobs\\FinishPayment');

    $this->actingAs($this->admin);

    Livewire::test(ListFailedJobs::class)
        ->assertCanSeeTableRecords([$retry, $clear])
        ->callTableAction('retry', $retry)
        ->callTableAction('forget', $clear);

    expect(FailedJob::count())->toBe(0);
});

it('sends an admin with no business of their own to the admin', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect('/admin');

    $owner = User::factory()->create();
    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('onboarding.create'));
});

it('grants and takes away admin from the command line', function () {
    $user = User::factory()->create(['email' => 'priya@example.com']);

    $this->artisan('admin:grant', ['email' => 'Priya@Example.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('admin:grant', ['email' => 'priya@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();

    $this->artisan('admin:grant', ['email' => 'nobody@example.com'])->assertFailed();

    $demo = User::factory()->create(['email' => 'demo@halfpast.test']);
    $demo->forceFill(['is_demo' => true])->save();
    $this->artisan('admin:grant', ['email' => 'demo@halfpast.test'])->assertFailed();
    expect($demo->fresh()->is_admin)->toBeFalse();
});
