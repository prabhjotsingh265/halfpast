<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Set once the person has their own login.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('bio', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'position']);
        });

        Schema::create('service_staff', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->primary(['service_id', 'staff_id']);
        });

        // Local clock times in the business's time zone. Several rows per day
        // are allowed, so a lunch break is just the gap between two of them.
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // ISO weekday: 1 is Monday, 7 is Sunday.
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');

            $table->index(['staff_id', 'weekday']);
        });

        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_order_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('staff');
    }
};
