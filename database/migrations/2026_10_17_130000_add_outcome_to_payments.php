<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // What settling decided once money arrived. Recorded before any
            // gateway call, so a retry carries on from here instead of
            // deciding again.
            $table->string('outcome', 24)->nullable()->after('status');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_outcome_check CHECK (outcome IS NULL OR outcome IN ('confirmed', 'slot_taken', 'duplicate', 'amount_mismatch', 'not_bookable', 'capture_failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_outcome_check');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
