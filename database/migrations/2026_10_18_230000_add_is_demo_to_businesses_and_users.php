<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The demo businesses and their logins, rebuilt every night. Nothing
        // else is ever touched by the reset.
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
