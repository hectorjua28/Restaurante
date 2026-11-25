<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('role:admin') o ->middleware('role:mesero,admin').
 * Responde 403 si el usuario autenticado no tiene alguno de los roles.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        abort_unless($user && $user->hasRole(...$allowed), 403, 'No tienes permiso para acceder a esta sección.');

        return $next($request);
    }
}
