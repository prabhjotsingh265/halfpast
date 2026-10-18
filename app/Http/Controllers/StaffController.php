<?php

namespace App\Http\Controllers;

use App\Actions\Staff\SaveStaffPhoto;
use App\Actions\Staff\SaveWeeklyHours;
use App\Http\Requests\StaffRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private SaveWeeklyHours $saveHours) {}

    public function index(): Response
    {
        $staff = Staff::with(['services', 'workingHours'])->orderBy('position')->orderBy('id')->get();

        return Inertia::render('staff/Index', [
            'staff' => $staff->map(fn (Staff $member) => [
                ...$this->present($member),
                'services' => $member->services->sortBy('position')->pluck('name')->values(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('staff/Form', [
            'member' => null,
            'services' => $this->services(),
        ]);
    }

    public function store(StaffRequest $request, SaveStaffPhoto $photos): RedirectResponse
    {
        $member = DB::transaction(function () use ($request) {
            $member = Staff::create([
                ...$request->staffAttributes(),
                'position' => (int) Staff::max('position') + 1,
            ]);

            $member->services()->sync($request->serviceIds());
            $this->saveHours->handle($member, $request->hours());

            return $member;
        });

        if ($request->hasFile('photo')) {
            $photos->handle($member, $request->file('photo'));
        }

        return redirect()->route('staff.index');
    }

    public function edit(Staff $staff): Response
    {
        $staff->load(['services', 'workingHours']);

        return Inertia::render('staff/Form', [
            'member' => $this->present($staff),
            'services' => $this->services(),
        ]);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        DB::transaction(function () use ($request, $staff) {
            $staff->update($request->staffAttributes());
            $staff->services()->sync($request->serviceIds());
            $this->saveHours->handle($staff, $request->hours());
        });

        return redirect()->route('staff.index');
    }

    public function destroy(Staff $staff, SaveStaffPhoto $photos): RedirectResponse
    {
        $photos->remove($staff);
        $staff->delete();

        return redirect()->route('staff.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Staff $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'bio' => $member->bio,
            'photo_url' => $member->photoUrl(),
            'is_active' => $member->is_active,
            'service_ids' => $member->services->pluck('id')->values(),
            'hours' => $member->workingHours->map(fn (WorkingHour $hour) => [
                'weekday' => $hour->weekday,
                // Postgres gives back "09:00:00"; the editor works in "09:00".
                'starts_at' => substr($hour->starts_at, 0, 5),
                'ends_at' => substr($hour->ends_at, 0, 5),
            ])->values(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, duration_minutes: int, is_active: bool}>
     */
    private function services(): array
    {
        return array_values(Service::orderBy('position')->orderBy('id')->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'is_active' => $service->is_active,
            ])
            ->all());
    }
}
