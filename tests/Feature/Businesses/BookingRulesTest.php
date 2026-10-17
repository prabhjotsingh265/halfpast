<?php

use App\Models\Business;

it('holds times for 15 minutes unless the business says otherwise', function () {
    expect((new Business)->hold_minutes)->toBe(15)
        ->and(Business::factory()->create()->fresh()->hold_minutes)->toBe(15);
});
