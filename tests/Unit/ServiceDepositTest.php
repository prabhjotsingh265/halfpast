<?php

use App\Enums\DepositType;
use App\Models\Service;

function serviceWithDeposit(DepositType $type, int $value, int $price = 3000): Service
{
    $service = new Service;
    $service->price = $price;
    $service->deposit_type = $type;
    $service->deposit_value = $value;

    return $service;
}

it('works out the deposit a customer pays', function (DepositType $type, int $value, int $expected) {
    expect(serviceWithDeposit($type, $value)->depositAmount())->toBe($expected);
})->with([
    'no deposit' => [DepositType::None, 500, 0],
    'fixed' => [DepositType::Fixed, 1000, 1000],
    'fixed, capped at the price' => [DepositType::Fixed, 9000, 3000],
    'percent' => [DepositType::Percent, 20, 600],
    'percent, rounded' => [DepositType::Percent, 33, 990],
    'percent, capped at 100' => [DepositType::Percent, 150, 3000],
]);
