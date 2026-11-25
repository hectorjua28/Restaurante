<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Detecta consultas N+1 durante el desarrollo y las pruebas.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Documentación OpenAPI (/docs/api): libre en local, solo administradores en producción.
        Gate::define('viewApiDocs', fn (?User $user) => $user?->isAdmin() ?? false);
    }
}
