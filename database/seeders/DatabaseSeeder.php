<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Model events stay on: they stamp
     * each record with its business and give bookings their codes.
     */
    public function run(): void
    {
        $this->call([DemoSeeder::class, AdminSeeder::class]);
    }
}
