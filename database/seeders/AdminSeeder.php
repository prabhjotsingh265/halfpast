<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A platform admin for working locally: admin@halfpast.test / password,
 * who can open /admin. Never made in production; there, grant yourself with
 * `php artisan admin:grant you@example.com`.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $admin = User::firstOrNew(['email' => 'admin@halfpast.test']);
        $admin->fill(['name' => 'Platform Admin', 'password' => 'password']);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
    }
}
