<?php

namespace Database\Seeders;

use App\Actions\Booking\FindBookableTimes;
use App\Actions\Booking\HoldTime;
use App\Actions\Businesses\CreateBusiness;
use App\Actions\Staff\SaveWeeklyHours;
use App\Enums\BookingStatus;
use App\Enums\DepositType;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A demo salon to click around: Kesar Beauty Lounge in Mumbai, with three
 * people on different hours, six services with different deposit rules, a
 * holiday, a closure and a few bookings already in the diary.
 *
 * Log in as demo@halfpast.test / password. The booking page is at
 * /kesar-beauty-lounge.
 */
class DemoSeeder extends Seeder
{
    public function run(CreateBusiness $create, SaveWeeklyHours $hours, FindBookableTimes $times, HoldTime $hold): void
    {
        $owner = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'demo@halfpast.test',
        ]);

        $business = $create->handle($owner, [
            'name' => 'Kesar Beauty Lounge',
            'slug' => 'kesar-beauty-lounge',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        // Stripe and Razorpay are offered when their test keys are in .env;
        // the test gateway works everywhere else.
        $business->update(['slot_step_minutes' => 15, 'min_notice_minutes' => 60, 'payment_gateways' => ['stripe', 'razorpay', 'test']]);
        app(CurrentBusiness::class)->set($business);

        // The owner was added as the first team member; give her real hours.
        $priya = Staff::where('user_id', $owner->id)->sole();
        $priya->update(['bio' => 'Cuts and colour, twelve years in the chair.']);
        $hours->handle($priya, self::week([2, 3, 4, 5, 6], [['10:00', '14:00'], ['15:00', '19:00']]));

        $aisha = Staff::create(['name' => 'Aisha Khan', 'bio' => 'Bridal and party make-up.', 'position' => 1]);
        $hours->handle($aisha, [
            ...self::week([3, 4, 5], [['11:00', '20:00']]),
            ...self::week([6, 7], [['10:00', '18:00']]),
        ]);

        $rohan = Staff::create(['name' => 'Rohan Mehta', 'bio' => 'Fades, beards and hot towel shaves.', 'position' => 2]);
        $hours->handle($rohan, self::week([1, 2, 3, 4, 5, 6], [['09:30', '17:30']]));

        $services = collect([
            ['Haircut & style', 'Wash, cut and blow-dry.', 45, 0, 80000, DepositType::Percent, 20, [$priya, $rohan]],
            ['Beard trim', 'Shape, line-up and hot towel.', 20, 0, 30000, DepositType::None, 0, [$rohan]],
            ['Eyebrow threading', 'Shape and tidy.', 15, 0, 15000, DepositType::None, 0, [$priya, $aisha]],
            ['Hair colour', 'Global colour with a gloss finish.', 120, 15, 350000, DepositType::Fixed, 70000, [$priya]],
            ['Classic facial', 'Cleanse, steam, mask and massage.', 60, 15, 150000, DepositType::Fixed, 30000, [$priya, $aisha]],
            ['Party make-up', 'Long-wear make-up for an evening out.', 90, 15, 250000, DepositType::Percent, 25, [$aisha]],
        ])->map(function (array $row, int $position) {
            [$name, $description, $duration, $buffer, $price, $depositType, $depositValue, $staff] = $row;

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
            $service->staff()->attach(array_map(fn (Staff $member) => $member->id, $staff));

            return $service;
        });

        $today = CarbonImmutable::now($business->timezone)->startOfDay();

        TimeOff::create([
            'staff_id' => $aisha->id,
            'starts_at' => $today->addDays(10)->utc(),
            'ends_at' => $today->addDays(13)->utc(),
            'reason' => 'Family wedding',
        ]);
        TimeOff::create([
            'starts_at' => $today->addDays(9)->utc(),
            'ends_at' => $today->addDays(10)->utc(),
            'reason' => 'Team training day',
        ]);

        $this->bookSomeTimes($business, $services->all(), $times, $hold);
    }

    /**
     * A few confirmed bookings over the next days, each placed the way a
     * customer would, so they always land on genuinely free times.
     *
     * @param  array<int, Service>  $services
     */
    private function bookSomeTimes(Business $business, array $services, FindBookableTimes $times, HoldTime $hold): void
    {
        $customers = collect([
            ['Ananya Iyer', 'ananya@example.com', '98200 11223'],
            ['Kabir Singh', 'kabir@example.com', '98330 44556'],
            ['Meera Nair', 'meera@example.com', '99870 77889'],
            ['Vikram Rao', 'vikram@example.com', '90040 99001'],
        ])->map(fn (array $c) => Customer::create(['name' => $c[0], 'email' => $c[1], 'phone' => $c[2]]));

        $from = CarbonImmutable::now($business->timezone)->addDay()->toDateString();
        $to = CarbonImmutable::now($business->timezone)->addDays(4)->toDateString();

        foreach ($services as $index => $service) {
            $slots = $times->handle($business, $service, null, $from, $to);
            // Spread them out: one early in the window, one later on.
            foreach ([intdiv(count($slots), 5), intdiv(count($slots) * 3, 5)] as $pick) {
                if (! isset($slots[$pick])) {
                    continue;
                }

                $booking = $hold->handle($business, $service, null, CarbonImmutable::instance($slots[$pick]->start));
                $booking->update([
                    'status' => BookingStatus::Confirmed,
                    'hold_expires_at' => null,
                    'customer_id' => $customers[($index + $pick) % $customers->count()]->id,
                ]);

                // Look again, so the second pick sees the first as taken.
                $slots = $times->handle($business, $service, null, $from, $to);
            }
        }
    }

    /**
     * @param  list<int>  $weekdays
     * @param  list<array{string, string}>  $stretches
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    private static function week(array $weekdays, array $stretches): array
    {
        $rows = [];

        foreach ($weekdays as $weekday) {
            foreach ($stretches as [$start, $end]) {
                $rows[] = ['weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end];
            }
        }

        return $rows;
    }
}
