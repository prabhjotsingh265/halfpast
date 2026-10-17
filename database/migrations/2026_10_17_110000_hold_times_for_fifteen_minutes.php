<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Times are now held for 15 minutes by default instead of 10. Paying by UPI
 * or card often means an OTP or approving in a bank app, and ten minutes
 * was tight for that. Businesses still on the old default move with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedSmallInteger('hold_minutes')->default(15)->change();
        });

        DB::table('businesses')->where('hold_minutes', 10)->update(['hold_minutes' => 15]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedSmallInteger('hold_minutes')->default(10)->change();
        });
    }
};
