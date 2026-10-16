<?php

namespace App\Enums;

enum BusinessRole: string
{
    /** Runs the business: services, staff, payments, settings. */
    case Owner = 'owner';

    /** Works there: sees and manages their own schedule. */
    case Staff = 'staff';
}
