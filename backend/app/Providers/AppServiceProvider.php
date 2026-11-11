<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Fuera de producción, usar una relación que no se cargó con with()/load() lanza un error
        // en vez de hacer una consulta por registro (N+1): las pruebas lo detectan solas.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
