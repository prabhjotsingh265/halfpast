<?php

use App\Actions\Booking\HoldTime;
use App\Actions\Staff\SaveWeeklyHours;
use App\Enums\BookingStatus;
use App\Exceptions\TimeNotAvailable;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Twenty customers press "Hold this time" for the same slot at the same
 * moment, each in their own process with its own database connection.
 * Every one of them sees the time as free when it checks; the database's
 * overlap rule has to let exactly one of them in.
 */

it('lets exactly one of twenty simultaneous holds through', function () {
    $business = Business::factory()->create(['timezone' => 'Europe/London', 'min_notice_minutes' => 0, 'slot_step_minutes' => 30]);
    app(CurrentBusiness::class)->set($business);

    $service = Service::factory()->for($business)->create(['duration_minutes' => 30, 'buffer_after_minutes' => 0]);
    $priya = Staff::factory()->for($business)->create();
    $service->staff()->attach($priya);
    app(SaveWeeklyHours::class)->handle($priya, array_map(
        fn (int $weekday) => ['weekday' => $weekday, 'starts_at' => '00:00', 'ends_at' => '24:00'],
        range(1, 7),
    ));

    $startsAt = CarbonImmutable::now('Europe/London')->addDay()->setTime(10, 0)->utc();
    $results = sys_get_temp_dir().'/halfpast-race-'.getmypid();
    @mkdir($results);

    // Each child opens its own connection; none may share the parent's.
    DB::disconnect();

    $go = microtime(true) + 2.0;
    $children = [];

    foreach (range(1, 20) as $customer) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                DB::connection()->getPdo();
                time_sleep_until($go);
                app(HoldTime::class)->handle($business, $service, null, $startsAt);
                $outcome = 'held';
            } catch (TimeNotAvailable) {
                $outcome = 'taken';
            } catch (Throwable $e) {
                $outcome = 'error: '.$e->getMessage();
            }

            file_put_contents("{$results}/{$customer}", $outcome);
            // Leave without running the test runner's shutdown code a second time.
            posix_kill(posix_getpid(), SIGKILL);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $outcomes = array_map(fn (string $file) => file_get_contents($file), glob("{$results}/*") ?: []);
    array_map('unlink', glob("{$results}/*") ?: []);
    rmdir($results);

    expect($outcomes)->toHaveCount(20)
        ->and(array_count_values($outcomes))->toEqual(['held' => 1, 'taken' => 19]);

    expect(Booking::where('status', BookingStatus::Held)->count())->toBe(1);
})->skip(fn () => ! function_exists('pcntl_fork'), 'Needs the pcntl extension.');
