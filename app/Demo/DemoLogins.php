<?php

namespace App\Demo;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Membership;
use App\Models\User;

/**
 * The one-click logins offered while demo mode is on: each demo business's
 * owner, and a team member where there is one.
 */
final class DemoLogins
{
    /**
     * @return list<array{slug: string, name: string, summary: string, logins: list<array{as: string, label: string}>}>
     */
    public static function available(): array
    {
        if (! config('demo.enabled')) {
            return [];
        }

        $summaries = array_column(Catalogue::businesses(), 'summary', 'slug');
        $order = array_flip(array_keys($summaries));

        return array_values(Business::where('is_demo', true)
            ->with(['members' => fn ($query) => $query->where('users.is_demo', true)])
            ->get()
            ->sortBy(fn (Business $business) => $order[$business->slug] ?? 99)
            ->map(fn (Business $business) => [
                'slug' => $business->slug,
                'name' => $business->name,
                'summary' => $summaries[$business->slug] ?? '',
                'logins' => array_values($business->members
                    ->sortBy(fn (User $member) => self::role($member) === BusinessRole::Owner ? 0 : 1)
                    ->map(fn (User $member) => [
                        'as' => self::role($member)->value,
                        'label' => strtok($member->name, ' ').(self::role($member) === BusinessRole::Owner ? ', the owner' : ', on the team'),
                    ])->all()),
            ])->all());
    }

    private static function role(User $member): BusinessRole
    {
        $membership = $member->getRelation('pivot');

        return $membership instanceof Membership ? $membership->role : BusinessRole::Staff;
    }
}
