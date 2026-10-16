<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            // Time kept free after the appointment, for cleaning up or notes.
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            // Money is whole minor units (pence, cents, paise), never floats.
            $table->unsignedInteger('price');
            $table->string('deposit_type')->default('none');
            $table->unsignedInteger('deposit_value')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
