<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:expire-holds')->everyMinute()->withoutOverlapping();

// The safety net under webhooks: payments nobody has heard back about.
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();

// Reminders 24 hours and 2 hours before each appointment.
Schedule::command('bookings:send-reminders')->everyMinute()->withoutOverlapping();

// The demo businesses, rebuilt around the new day so visitors' changes are put back.
Schedule::command('demo:reset')
    ->dailyAt((string) config('demo.reset_at'))
    ->timezone('UTC')
    ->when(fn () => (bool) config('demo.enabled'))
    ->withoutOverlapping();
