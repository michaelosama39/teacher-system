<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureRouteBindings();
    }

    private function configureAuthorization(): void
    {
        /*
         * The teacher owns the system and can do everything.
         */
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::Teacher) ? true : null);

        /*
         * Teacher-only abilities: no permission grants them, Gate::before does.
         */
        Gate::define('manage-assistants', fn (User $user): bool => false);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $identifier = (string) $request->input('identifier');
            $key = Phone::normalizeOrNull($identifier) ?? Str::lower(trim($identifier));

            return Limit::perMinute(5)->by($key.'|'.$request->ip());
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
    }

    private function configureRouteBindings(): void
    {
        Route::bind('assistant', fn (string $value): User => User::role(Role::Assistant)->findOrFail($value));
    }
}
