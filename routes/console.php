<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:expire-holds')->everyMinute()->withoutOverlapping();

// The safety net under webhooks: payments nobody has heard back about.
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();
