<?php

namespace App\Tenancy;

use App\Models\Business;
use LogicException;

/**
 * The business this request is for. On the dashboard it comes from the
 * logged-in person's membership; on a public booking page, from the address.
 * Bound once per request, so it never leaks between requests or jobs.
 */
class CurrentBusiness
{
    private ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function require(): Business
    {
        return $this->business ?? throw new LogicException('No current business has been set for this request.');
    }
}
