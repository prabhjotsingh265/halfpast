<?php

namespace App\Providers;

use App\Enums\BusinessRole;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request (and per queued job), so a business never leaks into the next one.
        $this->app->scoped(CurrentBusiness::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Livewire only drives the platform admin, which looks across every
        // business. Without this, its updates would pick up the business
        // remembered in the session and quietly show only that one's records.
        Livewire::setUpdateRoute(fn (array $handle) => Route::post('/livewire/update', $handle)
            ->middleware('web')
            ->withoutMiddleware([SetCurrentBusiness::class]));

        Gate::define('manage-business', function (User $user): bool {
            $business = app(CurrentBusiness::class)->get();

            return $business !== null && $user->roleIn($business) === BusinessRole::Owner;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
