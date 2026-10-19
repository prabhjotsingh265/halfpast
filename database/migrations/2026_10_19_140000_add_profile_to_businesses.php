<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a booking page says about the business, beyond its name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('description', 300)->nullable()->after('name');
            $table->string('address', 200)->nullable()->after('description');
            $table->string('phone', 40)->nullable()->after('address');
            $table->string('website', 200)->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['description', 'address', 'phone', 'website', 'logo_path']);
        });
    }
};
