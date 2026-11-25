<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas. En Hostinger se ejecutan con un cron cada minuto:
|   * * * * * cd /ruta/al/proyecto/web && php artisan schedule:run >> /dev/null 2>&1
| (ver DEPLOY.md)
*/

// Borra los tokens de la app que ya caducaron (ver SANCTUM_TOKEN_EXPIRATION).
Schedule::command('sanctum:prune-expired --hours=24')->daily()->at('04:00')->timezone(config('app.display_timezone'));

// Limpia tokens de restablecimiento de contraseña vencidos.
Schedule::command('auth:clear-resets')->daily()->at('04:10')->timezone(config('app.display_timezone'));

// Libera espacio de sesiones y caché expiradas guardadas en la base de datos.
Schedule::command('session:gc')->daily()->at('04:20')->timezone(config('app.display_timezone'));
Schedule::command('cache:prune-stale-tags')->hourly();
