<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Autenticación por token (Sanctum) para la app móvil.
 */
class AuthController extends Controller
{
    /**
     * Iniciar sesión.
     *
     * Devuelve un token Bearer. Límite: 5 intentos fallidos por minuto.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticatedUser();
        $token = $user->createToken($request->string('device_name', 'app')->value());

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /** Usuario autenticado. */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Cerrar sesión (revoca el token actual). */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
