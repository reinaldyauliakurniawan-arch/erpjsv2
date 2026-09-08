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
        // $this->authorize() calls in EnrollmentController, ClassSessionController,
        // JournalController, ClassroomController have no registered Policy. Laravel denies
        // (throws 403) when no policy/ability matches — it does NOT auto-pass.
        //
        // PENTING: pintu akses yang sebenarnya adalah RoleMiddleware per grup rute
        // (role:admin untuk /admin, role:cfo untuk /finance, dst). Baris di bawah
        // hanya jalan pintas supaya authorize() di controller tidak menolak staf
        // back-office yang SUDAH lolos middleware rute — bukan penentu akses.
        // Jangan mengandalkannya untuk rute tanpa RoleMiddleware.
        Gate::before(function ($user) {
            return $user->isBackOffice() ? true : null;
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
