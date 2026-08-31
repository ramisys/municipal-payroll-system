<?php

namespace App\Providers;

use App\Models\SystemConfig;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        $this->applyConfiguredSessionLifetime();

        View::composer('layouts.app', NavigationComposer::class);
    }

    // BR-32 — session.lifetime is a storage-level backstop behind
    // EnsureSessionIsNotIdle's deterministic per-request check, driven by
    // the same SYSTEM_CONFIG.SESSION_TIMEOUT_MINUTES value rather than a
    // hardcoded .env default (C-01). Guarded: this runs on every boot,
    // including `artisan migrate` before system_configs exists.
    private function applyConfiguredSessionLifetime(): void
    {
        try {
            if (! Schema::hasTable('system_configs')) {
                return;
            }

            $minutes = SystemConfig::value('SESSION_TIMEOUT_MINUTES');

            if ($minutes !== null) {
                config(['session.lifetime' => (int) $minutes]);
            }
        } catch (\Throwable) {
            // No database connection yet (e.g. key:generate before .env is
            // configured) — fall back to the .env-configured lifetime.
        }
    }
}
