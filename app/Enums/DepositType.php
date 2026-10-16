<?php

namespace App\Enums;

enum DepositType: string
{
    case None = 'none';

    /** A set amount, in minor units. */
    case Fixed = 'fixed';

    /** A share of the price, from 1 to 100. */
    case Percent = 'percent';
}
