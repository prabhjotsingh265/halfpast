<?php

namespace App\Listeners;

use App\Support\Heartbeat;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What /up checks, for an uptime monitor: the database and the cache
 * answer, and in production the scheduler has checked in lately. Anything
 * wrong turns /up into an error.
 */
class CheckHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::select('select 1');
        Cache::get('health-check');

        if (app()->isProduction() && ! Heartbeat::healthy()) {
            throw new RuntimeException('The scheduler has not run for over '.Heartbeat::STALE_AFTER_MINUTES.' minutes.');
        }
    }
}
