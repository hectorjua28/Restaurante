<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve la PWA de meseros en /app. Los archivos existentes los entrega el servidor web directamente;
 * aquí solo llegan las rutas internas de la app (p. ej. /app/tabs/mesas) y se responde con index.html.
 */
class MobileAppController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $index = public_path('app/index.html');

        abort_unless(is_file($index), 404, 'La app de meseros no está publicada. Ejecuta "npm run build:pwa" en /mobile.');

        return response()->file($index, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // index.html nunca se cachea para que las actualizaciones lleguen de inmediato.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
