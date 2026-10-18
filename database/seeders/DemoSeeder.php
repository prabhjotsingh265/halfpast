<?php

namespace Database\Seeders;

use App\Demo\ResetDemo;
use Illuminate\Database\Seeder;

/**
 * The three demo businesses (see App\Demo\Catalogue), built around today.
 * Log in with one click from the login page, or as demo@halfpast.test /
 * password. Their booking pages are /kesar-beauty-lounge, /fade-and-co and
 * /stillwater-physio.
 */
class DemoSeeder extends Seeder
{
    public function run(ResetDemo $reset): void
    {
        $reset->handle();
    }
}
