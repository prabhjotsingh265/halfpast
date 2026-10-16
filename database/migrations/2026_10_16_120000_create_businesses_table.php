<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The booking address: halfpast.example.com/{slug}
            $table->string('slug')->unique();
            $table->string('timezone');
            $table->char('currency', 3);
            $table->char('country', 2)->nullable();

            // Booking rules
            $table->unsignedSmallInteger('slot_step_minutes')->default(15);
            $table->unsignedSmallInteger('min_notice_minutes')->default(120);
            $table->unsignedSmallInteger('booking_horizon_days')->default(60);
            $table->unsignedSmallInteger('cancellation_window_hours')->default(24);
            $table->unsignedSmallInteger('hold_minutes')->default(10);

            $table->timestamps();
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('businesses');
    }
};
