<?php

namespace App\Demo;

use App\Models\Business;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Throws away the demo businesses and their logins and builds them again,
 * fresh around today. Only records marked as demo are ever deleted: if a
 * real business or person has taken a demo's address or email, that demo is
 * skipped rather than touching them.
 */
class ResetDemo
{
    public function __construct(private BuildDemoBusiness $build, private CurrentBusiness $current) {}

    /**
     * @return array{built: list<string>, skipped: list<string>}
     */
    public function handle(?int $daysBefore = null, ?int $daysAfter = null, ?CarbonImmutable $now = null): array
    {
        $daysBefore ??= (int) config('demo.days_before');
        $daysAfter ??= (int) config('demo.days_after');

        foreach (Business::where('is_demo', true)->get() as $business) {
            Storage::disk('public')->deleteDirectory("staff/{$business->id}");
            Storage::disk('public')->deleteDirectory("logos/{$business->id}");
            // Services, staff, bookings, payments and the rest go with it.
            $business->delete();
        }

        User::where('is_demo', true)->delete();

        $result = ['built' => [], 'skipped' => []];

        foreach (Catalogue::businesses() as $plan) {
            $emails = [$plan['owner']['email'], ...array_filter(array_column($plan['staff'], 'login'))];

            if (Business::where('slug', $plan['slug'])->exists() || User::whereIn('email', $emails)->exists()) {
                $result['skipped'][] = $plan['slug'];

                continue;
            }

            $this->build->handle($plan, $daysBefore, $daysAfter, $now);
            $result['built'][] = $plan['slug'];
        }

        $this->current->set(null);

        return $result;
    }
}
