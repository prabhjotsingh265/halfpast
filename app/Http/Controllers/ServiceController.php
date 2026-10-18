<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Payments\Gateways;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(CurrentBusiness $current, Gateways $gateways): Response
    {
        return Inertia::render('services/Index', [
            'canTakeDeposits' => $gateways->canTakeDeposits($current->require()),
            'services' => Service::withCount('staff')->orderBy('position')->orderBy('id')->get()->map(fn (Service $service) => [
                ...$this->present($service),
                'deposit' => $service->depositAmount(),
                'staff_count' => $service->staff_count,
            ]),
            'currency' => $current->require()->currency,
        ]);
    }

    public function create(CurrentBusiness $current): Response
    {
        return Inertia::render('services/Form', [
            'service' => null,
            'currency' => $current->require()->currency,
            'staff' => $this->staff(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $service = Service::create([
                ...$request->serviceAttributes(),
                'position' => (int) Service::max('position') + 1,
            ]);

            $service->staff()->sync($request->staffIds());
        });

        return redirect()->route('services.index');
    }

    public function edit(Service $service, CurrentBusiness $current): Response
    {
        return Inertia::render('services/Form', [
            'service' => [
                ...$this->present($service),
                'staff_ids' => $service->staff()->pluck('staff.id'),
            ],
            'currency' => $current->require()->currency,
            'staff' => $this->staff(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            $service->update($request->serviceAttributes());
            $service->staff()->sync($request->staffIds());
        });

        return redirect()->route('services.index');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('services.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'buffer_after_minutes' => $service->buffer_after_minutes,
            'price' => $service->price,
            'deposit_type' => $service->deposit_type->value,
            'deposit_value' => $service->deposit_value,
            'is_active' => $service->is_active,
        ];
    }

    /**
     * Everyone who could offer a service, for the "who does this" ticks.
     *
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    private function staff(): array
    {
        return array_values(Staff::orderBy('position')->orderBy('id')->get()
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'photo_url' => $member->photoUrl(),
                'is_active' => $member->is_active,
            ])
            ->all());
    }
}
