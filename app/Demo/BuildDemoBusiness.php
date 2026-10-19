<?php

namespace App\Demo;

use App\Actions\Businesses\CreateBusiness;
use App\Actions\Payments\SettlePayment;
use App\Actions\Staff\SaveWeeklyHours;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\BookingConfirmed;
use App\Events\CalendarChanged;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingReminder;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Payments\CheckoutLinks;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds one demo business from the catalogue, the way it would look after
 * a few weeks of real use: two weeks of appointments behind it (mostly
 * done, a few no-shows and cancellations, the odd one nobody has marked
 * yet), today's diary, and bookings coming up, thinning out further ahead.
 *
 * Deposits go through the test gateway exactly as a real checkout would,
 * so the earnings add up and a refund on a demo booking really works. The
 * same day always builds the same diary.
 */
class BuildDemoBusiness
{
    private Randomizer $random;

    private CarbonImmutable $now;

    /** Unmarked appointments left over from yesterday and today, for the Today page. */
    private int $leftUnmarked = 0;

    /** Who marks visits for team members without a login of their own. */
    private int $ownerId = 0;

    public function __construct(
        private CreateBusiness $create,
        private SaveWeeklyHours $hours,
        private CurrentBusiness $current,
        private TestGateway $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $plan  One of Catalogue::businesses().
     */
    public function handle(array $plan, int $daysBefore, int $daysAfter, ?CarbonImmutable $now = null): Business
    {
        $this->now = $now ?? CarbonImmutable::now();
        $this->random = new Randomizer(new Mt19937(crc32($plan['slug'].$this->now->toDateString())));
        $this->leftUnmarked = 0;

        $owner = $this->demoUser($plan['owner']['name'], $plan['owner']['email']);
        $this->ownerId = $owner->id;

        $business = $this->create->handle($owner, [
            'name' => $plan['name'],
            'slug' => $plan['slug'],
            'timezone' => $plan['timezone'],
            'currency' => $plan['currency'],
        ]);
        $business->update([...$plan['settings'], ...($plan['profile'] ?? [])]);
        $business->forceFill(['is_demo' => true])->save();
        $this->current->set($business);

        $staff = $this->team($business, $owner, $plan['staff']);
        $services = $this->services($plan['services'], $staff);
        $customers = [];

        foreach ($plan['customers'] as [$name, $email, $phone, $notes]) {
            $customers[] = Customer::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes]);
        }

        $this->timeOff($business, $plan['time_off'], $staff);

        // A diary full of bookings would otherwise send a live update for each one.
        Event::fakeFor(fn () => $this->diary($business, $staff, $services, $customers, $daysBefore, $daysAfter), [CalendarChanged::class, BookingConfirmed::class]);

        return $business;
    }

    private function demoUser(string $name, string $email): User
    {
        // Not a factory: those need Faker, which production doesn't install.
        $user = new User(['name' => $name, 'email' => $email, 'password' => Catalogue::PASSWORD]);
        $user->forceFill(['is_demo' => true, 'email_verified_at' => $this->now])->save();

        return $user;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return array<string, Staff>
     */
    private function team(Business $business, User $owner, array $plans): array
    {
        $staff = [];

        foreach ($plans as $position => $plan) {
            if ($plan['owner'] ?? false) {
                $member = Staff::where('user_id', $owner->id)->sole();
                $member->update(['bio' => $plan['bio'], 'position' => $position]);
            } else {
                $member = Staff::create(['name' => $plan['name'], 'bio' => $plan['bio'], 'position' => $position]);
            }

            // A staff login, to see the app the way staff do.
            if (isset($plan['login'])) {
                $user = $this->demoUser($plan['name'], $plan['login']);
                $business->addMember($user, BusinessRole::Staff);
                $member->update(['user_id' => $user->id]);
            }

            $rows = [];

            foreach ($plan['hours'] as [$weekdays, $stretches]) {
                foreach ($weekdays as $weekday) {
                    foreach ($stretches as [$from, $to]) {
                        $rows[] = ['weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to];
                    }
                }
            }

            $this->hours->handle($member, $rows);
            $staff[$plan['key']] = $member->fresh() ?? $member;
        }

        return $staff;
    }

    /**
     * @param  list<array<int, mixed>>  $plans
     * @param  array<string, Staff>  $staff
     * @return list<Service>
     */
    private function services(array $plans, array $staff): array
    {
        $services = [];

        foreach ($plans as $position => [$name, $description, $duration, $buffer, $price, $depositType, $depositValue, $who]) {
            $service = Service::create([
                'name' => $name,
                'description' => $description,
                'duration_minutes' => $duration,
                'buffer_after_minutes' => $buffer,
                'price' => $price,
                'deposit_type' => $depositType,
                'deposit_value' => $depositValue,
                'position' => $position,
            ]);
            $service->staff()->attach(array_map(fn (string $key) => $staff[$key]->id, $who));
            $services[] = $service->load('staff');
        }

        return $services;
    }

    /**
     * @param  list<array{staff: string|null, from: int, days: int, reason: string}>  $plans
     * @param  array<string, Staff>  $staff
     */
    private function timeOff(Business $business, array $plans, array $staff): void
    {
        $today = $this->now->setTimezone($business->timezone)->startOfDay();

        foreach ($plans as $plan) {
            TimeOff::create([
                'staff_id' => $plan['staff'] === null ? null : $staff[$plan['staff']]->id,
                'starts_at' => $today->addDays($plan['from'])->utc(),
                'ends_at' => $today->addDays($plan['from'] + $plan['days'])->startOfDay()->utc(),
                'reason' => $plan['reason'],
            ]);
        }
    }

    /**
     * Walks each person's working hours day by day, filling some of the
     * time with appointments that fit, the way a real diary fills up.
     *
     * @param  array<string, Staff>  $staff
     * @param  list<Service>  $services
     * @param  list<Customer>  $customers
     */
    private function diary(Business $business, array $staff, array $services, array $customers, int $daysBefore, int $daysAfter): void
    {
        $zone = $business->timezone;
        $today = $this->now->setTimezone($zone)->startOfDay();
        $off = TimeOff::all();
        $step = $business->slot_step_minutes;

        for ($offset = -$daysBefore; $offset <= $daysAfter; $offset++) {
            $day = $today->addDays($offset);
            // Busy behind and today, quieter the further ahead.
            $fill = $offset <= 0 ? 62 : max(18, 62 - 3 * $offset);

            foreach ($staff as $member) {
                $theirs = array_values(array_filter($services, fn (Service $s) => $s->staff->contains('id', $member->id)));
                $stretches = WorkingHour::where('staff_id', $member->id)->where('weekday', $day->isoWeekday())->orderBy('starts_at')->get();

                foreach ($stretches as $stretch) {
                    $cursor = $day->setTimeFromTimeString($stretch->starts_at);
                    $end = $stretch->ends_at === '24:00:00' ? $day->addDay() : $day->setTimeFromTimeString($stretch->ends_at);

                    while ($cursor->lessThan($end)) {
                        $fits = array_values(array_filter($theirs, fn (Service $s) => $cursor->addMinutes($s->duration_minutes + $s->buffer_after_minutes)->lessThanOrEqualTo($end)));
                        $away = $off->first(fn (TimeOff $t) => ($t->staff_id === null || $t->staff_id === $member->id) && $t->starts_at->lessThan($cursor->addMinutes(15)->utc()) && $t->ends_at->greaterThan($cursor->utc()));

                        if ($fits === [] || $away !== null || $this->random->getInt(1, 100) > $fill) {
                            $cursor = $cursor->addMinutes($step);

                            continue;
                        }

                        $service = $fits[$this->random->getInt(0, count($fits) - 1)];
                        $this->book($business, $member, $service, $customers, $cursor, $offset);
                        // A little breathing room now and then.
                        $cursor = $cursor->addMinutes($service->duration_minutes + $service->buffer_after_minutes + ($this->random->getInt(1, 3) === 1 ? $step : 0));
                    }
                }
            }
        }
    }

    /**
     * @param  list<Customer>  $customers
     */
    private function book(Business $business, Staff $member, Service $service, array $customers, CarbonImmutable $start, int $offset): void
    {
        $startsAt = $start->utc();
        $endsAt = $startsAt->addMinutes($service->duration_minutes);
        $manual = $this->random->getInt(1, 100) <= 15;
        // The first few customers are regulars and come back more often.
        $customer = $manual && $this->random->getInt(1, 5) === 1
            ? null
            : $customers[$this->random->getInt(1, 3) === 1 ? $this->random->getInt(0, 3) : $this->random->getInt(0, count($customers) - 1)];
        $deposit = $manual ? 0 : $service->depositAmount();

        // Online bookings are made days ahead; phone calls and walk-ins nearer the time.
        $createdAt = $startsAt->subMinutes($manual ? $this->random->getInt(10, 3 * 24 * 60) : $this->random->getInt(6 * 60, 12 * 24 * 60));
        $createdAt = $createdAt->greaterThan($this->now) ? $this->now->subMinutes($this->random->getInt(5, 120)) : $createdAt;

        $status = $this->statusFor($startsAt, $endsAt, $offset);
        $early = $status === BookingStatus::Cancelled && $this->random->getInt(1, 2) === 1;

        $booking = new Booking([
            'service_id' => $service->id,
            'staff_id' => $member->id,
            'customer_id' => $customer?->id,
            'service_name' => $service->name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'blocked_until' => $endsAt->addMinutes($service->buffer_after_minutes),
            'status' => $status,
            'source' => $manual ? 'manual' : 'online',
            'price' => $service->price,
            'deposit' => $deposit,
            'currency' => $business->currency,
        ]);
        $booking->business_id = $business->id;
        $booking->forceFill([
            'cancelled_at' => $status === BookingStatus::Cancelled ? self::earliest($early ? $createdAt->addHours(6) : $startsAt->subHours(3), $this->now) : null,
            'cancelled_by' => $status === BookingStatus::Cancelled ? 'customer' : null,
            'refund_due' => $early ? $deposit : 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $booking->save();

        $this->history($booking, $member, $createdAt);
        $payment = $deposit > 0 ? $this->depositPaid($booking, $createdAt->addMinutes(2)) : null;

        if ($payment !== null && $early) {
            $this->refunded($payment, $booking->cancelled_at ?? $this->now);
        }

        $this->reminders($booking, $customer, $createdAt);
    }

    private function statusFor(CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $offset): BookingStatus
    {
        if ($startsAt->greaterThan($this->now)) {
            return $this->random->getInt(1, 100) <= 5 ? BookingStatus::Cancelled : BookingStatus::Confirmed;
        }

        if ($endsAt->greaterThan($this->now)) {
            return BookingStatus::Confirmed;
        }

        // A handful from yesterday and today nobody has said how they went yet.
        if ($offset >= -1 && $this->leftUnmarked < 4 && $this->random->getInt(1, 3) === 1) {
            $this->leftUnmarked++;

            return BookingStatus::Confirmed;
        }

        $roll = $this->random->getInt(1, 100);

        return match (true) {
            $roll <= 7 => BookingStatus::NoShow,
            $roll <= 13 => BookingStatus::Cancelled,
            default => BookingStatus::Completed,
        };
    }

    /** What happened to it and when, as if each step had happened for real. */
    private function history(Booking $booking, Staff $member, CarbonImmutable $createdAt): void
    {
        $booking->events()->update(['created_at' => $createdAt]);
        $by = $member->user_id ?? $this->ownerId;

        $event = fn (string $type, CarbonImmutable $at, ?int $user = null, ?array $meta = null) => BookingEvent::create([
            'business_id' => $booking->business_id,
            'booking_id' => $booking->id,
            'type' => $type,
            'user_id' => $user,
            'meta' => $meta,
            'created_at' => self::earliest($at, $this->now),
        ]);

        $event('confirmed', $createdAt->addMinutes(2));

        match ($booking->status) {
            BookingStatus::Completed => $event('completed', $booking->ends_at->addMinutes($this->random->getInt(5, 90)), $by),
            BookingStatus::NoShow => $event('no_show', $booking->starts_at->addMinutes(30), $by),
            BookingStatus::Cancelled => $event('cancelled', CarbonImmutable::instance($booking->cancelled_at ?? $this->now), null, ['by' => 'customer', 'refund_due' => $booking->refund_due]),
            default => null,
        };
    }

    /** A deposit taken through the test gateway, the way checkout takes one. */
    private function depositPaid(Booking $booking, CarbonImmutable $at): Payment
    {
        $payment = new Payment(['booking_id' => $booking->id, 'gateway' => 'test', 'amount' => $booking->deposit, 'currency' => $booking->currency, 'status' => PaymentStatus::Pending]);
        $payment->business_id = $booking->business_id;
        $payment->save();

        $this->gateway->begin($payment, $booking, new CheckoutLinks(url('/'), url('/')));
        $this->gateway->pay((string) $payment->gateway_order_id);
        $this->gateway->capture($payment);

        $payment->forceFill([
            'status' => PaymentStatus::Captured,
            'outcome' => PaymentOutcome::Confirmed,
            'gateway_payment_id' => $this->gateway->load((string) $payment->gateway_order_id)['payment_id'],
            'authorized_at' => $at,
            'captured_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        SettlePayment::recordDeposit($payment);
        LedgerEntry::withoutGlobalScopes()->where('payment_id', $payment->id)->update(['created_at' => $at]);

        return $payment;
    }

    /** A cancellation in good time: the deposit went back. */
    private function refunded(Payment $payment, mixed $at): void
    {
        $at = CarbonImmutable::instance($at);
        $refund = new Refund(['payment_id' => $payment->id, 'amount' => $payment->amount, 'status' => RefundStatus::Pending, 'reason' => 'customer_cancelled']);
        $refund->business_id = $payment->business_id;
        $refund->save();

        $refundId = $this->gateway->refund($payment, $payment->amount, 'halfpast-refund-'.$refund->id);
        $refund->forceFill(['status' => RefundStatus::Succeeded, 'gateway_refund_id' => $refundId, 'attempts' => 1, 'created_at' => $at, 'updated_at' => $at])->save();
        $payment->forceFill(['refunded_amount' => $payment->amount, 'status' => PaymentStatus::Refunded])->save();

        (new LedgerEntry)->forceFill([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'refund_id' => $refund->id,
            'type' => 'refund',
            'amount' => -$payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            'created_at' => $at,
        ])->save();
    }

    /** Reminders that would have gone out by now. */
    private function reminders(Booking $booking, ?Customer $customer, CarbonImmutable $createdAt): void
    {
        if ($customer?->email === null || $booking->status === BookingStatus::Cancelled) {
            return;
        }

        foreach (['24h' => 24 * 60, '2h' => 120] as $kind => $minutes) {
            $due = $booking->starts_at->subMinutes($minutes);

            if ($due->greaterThan($createdAt) && $due->lessThanOrEqualTo($this->now)) {
                (new BookingReminder)->forceFill(['business_id' => $booking->business_id, 'booking_id' => $booking->id, 'kind' => $kind, 'sent_at' => $due])->save();
            }
        }
    }

    private static function earliest(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
