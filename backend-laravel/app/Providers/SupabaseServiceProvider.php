<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SupabaseService;

class SupabaseServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios en el contenedor.
     */
    public function register(): void
    {
        $this->app->singleton(SupabaseService::class, function ($app) {
            return new SupabaseService();
        });
    }

    /**
     * Arranca cualquier servicio de la aplicación.
     */
    public function boot(): void
    {
        //
    }
}
