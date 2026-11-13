<?php

namespace App\Providers;

use App\Modules\Asistente\IA\ClienteGemini;
use App\Modules\Asistente\IA\ClienteGroq;
use App\Modules\Asistente\IA\ClienteIA;
use App\Modules\Asistente\IA\IaNoDisponible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Proveedor de IA del asistente, según el .env (ver config/services.php, clave "ia").
        $this->app->bind(ClienteIA::class, function (): ClienteIA {
            $ia = config('services.ia');
            $clase = match ($ia['proveedor']) {
                'gemini' => ClienteGemini::class,
                'groq' => ClienteGroq::class,
                default => throw new IaNoDisponible("IA_PROVEEDOR «{$ia['proveedor']}» no existe: usa gemini o groq."),
            };

            return new $clase($ia['clave'], $ia['modelo'] ?: $clase::MODELO_POR_DEFECTO, $ia['tiempo_limite']);
        });
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
