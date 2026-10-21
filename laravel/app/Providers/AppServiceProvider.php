<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Admins pass every policy check. Returning null (not false) lets the
        // normal policy evaluation continue for managers and clients.
        Gate::before(function (User $user) {
            return $user->role === Role::Admin ? true : null;
        });
    }
}
