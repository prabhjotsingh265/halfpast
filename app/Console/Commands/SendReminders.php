<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingReminderMail;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds customers 24 hours and 2 hours before their appointment. Runs every
 * minute and looks a little ahead, so a missed minute never loses a
 * reminder. Each one is recorded before it's queued, under a unique rule, so
 * nobody gets the same reminder twice.
 */
#[Signature('bookings:send-reminders')]
#[Description('Send 24-hour and 2-hour appointment reminders')]
class SendReminders extends Command
{
    /**
     * Hours before the start => a booking made later than this many hours
     * before the start skips that reminder (they've only just had their
     * confirmation).
     *
     * @var array<string, array{hours: int, bookedBefore: int}>
     */
    public const KINDS = [
        '24h' => ['hours' => 24, 'bookedBefore' => 25],
        '2h' => ['hours' => 2, 'bookedBefore' => 3],
    ];

    /** How far ahead of the exact moment a reminder may go. */
    public const WINDOW_MINUTES = 60;

    public function handle(): int
    {
        $sent = 0;

        foreach (self::KINDS as $kind => ['hours' => $hours, 'bookedBefore' => $bookedBefore]) {
            $from = now()->addHours($hours)->subMinutes(self::WINDOW_MINUTES);
            $until = now()->addHours($hours);

            Booking::withoutGlobalScopes()
                ->where('status', BookingStatus::Confirmed)
                ->whereBetween('starts_at', [$from, $until])
                ->whereRaw('created_at <= starts_at - make_interval(hours => ?)', [$bookedBefore])
                ->whereHas('customer', fn ($query) => $query->withoutGlobalScopes()->whereNotNull('email'))
                ->whereDoesntHave('reminders', fn ($query) => $query->where('kind', $kind))
                ->with('customer')
                ->orderBy('starts_at')
                ->limit(500)
                ->get()
                ->each(function (Booking $booking) use ($kind, &$sent) {
                    if ($this->record($booking, $kind)) {
                        $sent++;
                    }
                });
        }

        $this->info("Sent {$sent} ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }

    /**
     * Records the reminder and queues it together. If another run got there
     * first, the unique rule refuses the row and nothing is sent.
     */
    private function record(Booking $booking, string $kind): bool
    {
        try {
            DB::transaction(function () use ($booking, $kind) {
                DB::table('booking_reminders')->insert([
                    'business_id' => $booking->business_id,
                    'booking_id' => $booking->id,
                    'kind' => $kind,
                    'sent_at' => now(),
                ]);

                $customer = $booking->customer;

                if ($customer?->email !== null) {
                    Mail::to($customer->email, $customer->name)->send(new BookingReminderMail($booking, $kind));
                }
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
