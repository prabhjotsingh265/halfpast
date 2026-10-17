<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable()->after('notes');
            // "customer" or "business".
            $table->string('cancelled_by', 20)->nullable()->after('cancelled_at');
            // How much of the deposit goes back, in minor units. The payment
            // gateway refunds it; 0 when the deposit is kept or there wasn't one.
            $table->unsignedInteger('refund_due')->default(0)->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'refund_due']);
        });
    }
};
