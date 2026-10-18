<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case
|--------------------------------------------------------------------------
|
| Feature tests run inside the Laravel app with a fresh database each time.
| Unit tests stay plain PHP, so code like the availability engine is tested
| without booting the framework.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// These run several processes against the database at once, so their data
// has to be committed instead of rolled back. The tables are emptied before
// each test, and after it too: otherwise what it committed would still be
// there for whichever feature test runs next.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->afterEach(fn () => $this->truncateDatabaseTables())
    ->in('Concurrency');
