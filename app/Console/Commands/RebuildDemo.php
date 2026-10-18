<?php

namespace App\Console\Commands;

use App\Demo\ResetDemo;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset {--force : Rebuild even when demo mode is off}')]
#[Description('Throw away the demo businesses and build them again around today')]
class RebuildDemo extends Command
{
    public function handle(ResetDemo $reset): int
    {
        if (! config('demo.enabled') && ! $this->option('force')) {
            $this->error('Demo mode is off (DEMO_MODE). Use --force to rebuild anyway.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $result = $reset->handle();

        foreach ($result['built'] as $slug) {
            $business = Business::where('slug', $slug)->sole();
            $this->line(sprintf('  %s: %d bookings', $business->name, Booking::withoutGlobalScopes()->where('business_id', $business->id)->count()));
        }

        foreach ($result['skipped'] as $slug) {
            $this->warn("  Skipped {$slug}: its address or a login is taken by a real account.");
        }

        $this->info(sprintf('Demo rebuilt in %.1fs.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
