<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeOffRequest;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffController extends Controller
{
    public function index(Request $request, CurrentBusiness $current): Response
    {
        $zone = $current->require()->timezone;

        $entries = TimeOff::with('staff')
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('time-off/Index', [
            'entries' => $entries->map(fn (TimeOff $entry) => $this->present($entry, $zone))->values(),
            'staff' => Staff::orderBy('position')->orderBy('id')->get()
                ->map(fn (Staff $member) => ['id' => $member->id, 'name' => $member->name, 'photo_url' => $member->photoUrl(), 'is_active' => $member->is_active])
                ->values(),
            'today' => now($zone)->toDateString(),
            'timezone' => $zone,
            // Coming from someone's card on the team page.
            'preselectedStaffId' => $request->integer('staff') ?: null,
        ]);
    }

    public function store(TimeOffRequest $request): RedirectResponse
    {
        [$start, $end] = $request->period();

        TimeOff::create([
            'staff_id' => $request->validated('staff_id'),
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => $request->validated('reason'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time off added.']);

        return redirect()->route('time-off.index');
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time off removed.']);

        return redirect()->route('time-off.index');
    }

    /**
     * Dates and times as the clock reads where the business is. The last day
     * is inclusive, so a holiday ending at midnight shows the day before.
     *
     * @return array<string, mixed>
     */
    private function present(TimeOff $entry, string $zone): array
    {
        $start = $entry->starts_at->setTimezone($zone);
        $end = $entry->ends_at->setTimezone($zone);
        $allDay = $start->format('H:i') === '00:00' && $end->format('H:i') === '00:00';

        return [
            'id' => $entry->id,
            'staff_id' => $entry->staff_id,
            'staff_name' => $entry->staff?->name,
            'staff_photo_url' => $entry->staff?->photoUrl(),
            'reason' => $entry->reason,
            'all_day' => $allDay,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->subSecond()->toDateString(),
            'starts_time' => $allDay ? null : $start->format('H:i'),
            'ends_time' => $allDay ? null : $end->format('H:i'),
            'started' => $entry->starts_at->isPast(),
        ];
    }
}
