<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si un administrador desactiva a un usuario, su sesión o token deja de servir de inmediato.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            } else {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }

            throw new AuthenticationException('Usuario desactivado.');
        }

        return $next($request);
    }
}
