<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per reminder sent. The unique pair is what makes sure a
        // customer never gets the same reminder twice.
        Schema::create('booking_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // "24h" or "2h".
            $table->string('kind', 10);
            $table->timestampTz('sent_at')->useCurrent();

            $table->unique(['booking_id', 'kind']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'starts_at']);
        });

        Schema::dropIfExists('booking_reminders');
    }
};
