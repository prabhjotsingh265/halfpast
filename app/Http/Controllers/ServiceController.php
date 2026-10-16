<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(CurrentBusiness $current): Response
    {
        return Inertia::render('services/Index', [
            'services' => Service::orderBy('position')->orderBy('id')->get()->map(fn (Service $service) => [
                ...$this->present($service),
                'deposit' => $service->depositAmount(),
            ]),
            'currency' => $current->require()->currency,
        ]);
    }

    public function create(CurrentBusiness $current): Response
    {
        return Inertia::render('services/Form', [
            'service' => null,
            'currency' => $current->require()->currency,
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create([
            ...$request->serviceAttributes(),
            'position' => (int) Service::max('position') + 1,
        ]);

        return redirect()->route('services.index');
    }

    public function edit(Service $service, CurrentBusiness $current): Response
    {
        return Inertia::render('services/Form', [
            'service' => $this->present($service),
            'currency' => $current->require()->currency,
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->serviceAttributes());

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
}
