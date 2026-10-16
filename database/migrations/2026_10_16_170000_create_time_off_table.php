<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real moments in UTC. No staff member means the whole business is closed.
        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE time_off ADD CONSTRAINT time_off_order_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off');
    }
};
