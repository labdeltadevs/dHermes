<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions\TenantRoleSyncer;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class, fn (): CurrentTenant => new CurrentTenant(
            $this->app->make(PermissionRegistrar::class),
        ));

        $this->app->scoped(TenantRoleSyncer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Grant the platform administrator every ability.
     *
     * Tenant isolation is still enforced by the global tenant scope, so this
     * only removes the authorization gate; data remains scoped to the
     * impersonated tenant while one is active.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (?User $user): ?bool => $user instanceof User && $user->active && $user->is_platform_admin
            ? true
            : null);
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
