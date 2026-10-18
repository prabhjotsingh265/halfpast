<?php

namespace App\Support\Calendar;

use App\Models\Service;
use App\Models\Staff;

/**
 * What the new-booking panel can offer the person looking: the services on
 * sale with who does each, and who they can book in (anyone for an owner,
 * themselves for staff).
 */
final class NewBookingOptions
{
    /**
     * @return array{services: list<array<string, mixed>>, staff: list<array{id: int, name: string}>, currency: string, can_close: bool}
     */
    public static function for(Viewer $viewer): array
    {
        $services = Service::where('is_active', true)
            ->with(['staff' => fn ($query) => $query->where('staff.is_active', true)->select('staff.id')])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $staff = Staff::where('is_active', true)
            ->when(! $viewer->isOwner, fn ($query) => $query->whereIn('id', $viewer->staffIds))
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name']);

        return [
            'services' => array_values($services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration' => $service->duration_minutes,
                'price' => $service->price,
                'staff_ids' => $service->staff->pluck('id')->values()->all(),
            ])->all()),
            'staff' => array_values($staff->map(fn (Staff $member) => ['id' => $member->id, 'name' => $member->name])->all()),
            'currency' => $viewer->business->currency,
            // Owners can block out time for the whole business.
            'can_close' => $viewer->isOwner,
        ];
    }
}
