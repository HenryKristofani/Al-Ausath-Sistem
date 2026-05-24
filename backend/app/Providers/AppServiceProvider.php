<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble; // <-- Tambahkan ini
use Illuminate\Routing\Route; // <-- Tambahkan ini
use Illuminate\Support\Str; // <-- Tambahkan ini
use Illuminate\Support\Facades\Schedule;
use App\Models\SppSetting;
use App\Models\DataSantri;
use App\Support\SppBillingService;

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
        // Tambahkan kode ini untuk mengajari Scramble rute mana yang harus dibaca
        Scramble::routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/') || in_array($route->uri, ['login', 'logout']);
        });

        // Schedule SPP billing provisioning - CRITICAL FIX
        $this->scheduleSpppProvisioning();

        // Register SppSetting observer for instant provisioning
        SppSetting::observe(\App\Observers\SppSettingObserver::class);
        \App\Models\SppGolongan::observe(\App\Observers\SppGolonganObserver::class);
        \App\Models\DataSantri::observe(\App\Observers\DataSantriObserver::class);
        \App\Models\JadwalPembelajaran::observe(\App\Observers\JadwalPembelajaranObserver::class);
    }

    /**
     * Schedule SPP billing provisioning daily.
     * This ensures bills are automatically created for active santri.
     */
    protected function scheduleSpppProvisioning(): void
    {
        Schedule::command('spp:provision-bills')
            ->daily()
            ->at('01:00') // Run at 1 AM server time
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('SPP billing provisioning completed successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('SPP billing provisioning failed');
            });
    }
}