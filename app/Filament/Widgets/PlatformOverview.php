<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\FailedJob;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The platform at a glance, at the top of the admin.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $real = Business::where('is_demo', false)->count();
        $demo = Business::where('is_demo', true)->count();
        $week = Booking::withoutGlobalScopes()->where('created_at', '>=', now()->subDays(7))->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow]);
        $paid = Payment::withoutGlobalScopes()->where('captured_at', '>=', now()->subDays(7))->whereIn('status', [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded]);
        $failed = FailedJob::count();
        $waiting = PaymentEvent::whereNull('processed_at')->where('created_at', '<', now()->subMinutes(5))->count();

        return [
            Stat::make('Businesses', (string) $real)->description($demo > 0 ? "and {$demo} demo" : 'none are demos'),
            Stat::make('Booked in the last 7 days', (string) $week->count())->description('online and by hand'),
            Stat::make('Deposits taken in the last 7 days', (string) $paid->count())->description('payments captured'),
            Stat::make('Failed jobs', (string) $failed)
                ->description($failed > 0 ? 'need a look' : 'all clear')
                ->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Webhooks waiting', (string) $waiting)
                ->description($waiting > 0 ? 'over 5 minutes old' : 'all dealt with')
                ->color($waiting > 0 ? 'warning' : 'success'),
        ];
    }
}
