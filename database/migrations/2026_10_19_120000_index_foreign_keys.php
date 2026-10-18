<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PostgreSQL doesn't index foreign keys by itself. Some of these are looked
 * up on nearly every request (which businesses a person belongs to, which
 * team member they are), others whenever a record is deleted, since the
 * database has to find what pointed at it. Plus staff and time together,
 * which the slot finder filters on for every day it works out.
 */
return new class extends Migration
{
    /** @var array<string, list<string|list<string>>> */
    private const INDEXES = [
        'business_user' => ['user_id'],
        'staff' => ['user_id'],
        'service_staff' => ['staff_id'],
        'working_hours' => ['business_id'],
        'time_off' => ['staff_id'],
        'bookings' => ['customer_id', 'service_id', ['staff_id', 'starts_at']],
        'payments' => ['business_id'],
        'refunds' => ['business_id', 'payment_id'],
        'ledger_entries' => ['refund_id'],
        'booking_reminders' => ['business_id'],
        'booking_events' => ['business_id', 'user_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex((array) $columns);
                }
            });
        }
    }
};
