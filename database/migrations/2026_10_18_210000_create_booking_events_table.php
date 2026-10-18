<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What happened to a booking and who did it: made, confirmed,
        // marked, cancelled. Payments, refunds and reminders keep their own
        // records; together they make the booking's history.
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // "created", or the status it moved to.
            $table->string('type', 20);
            // Whoever was logged in; null for customers and the system.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
    }
};
