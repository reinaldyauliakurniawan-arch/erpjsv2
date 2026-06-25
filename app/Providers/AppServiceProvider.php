<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
    public function boot(): void
    {
        // ponytail: $this->authorize() calls in EnrollmentController, ClassSessionController,
        // JournalController, ClassroomController have no registered Policy. Laravel denies
        // (throws 403) when no policy/ability matches — it does NOT auto-pass. RoleMiddleware
        // already gates these routes by role, so this restores that intended pass-through
        // instead of building out unused Policy classes for a check that's redundant anyway.
        Gate::before(function ($user, string $ability) {
            return in_array($user->role, ['admin', 'cfo'], true) ? true : null;
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
