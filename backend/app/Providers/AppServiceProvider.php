<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble; // <-- Tambahkan ini
use Illuminate\Routing\Route; // <-- Tambahkan ini
use Illuminate\Support\Str; // <-- Tambahkan ini

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
    }
}