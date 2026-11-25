<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('mesero@pinguin.test|127.0.0.1'));

it('entrega un token al iniciar sesión con credenciales válidas', function () {
    waiter(['email' => 'mesero@pinguin.test', 'name' => 'Luis']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'mesero@pinguin.test',
        'password' => 'password',
        'device_name' => 'Android de Luis',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'role_label']])
        ->assertJsonPath('user.role', 'mesero')
        ->assertJsonPath('user.name', 'Luis');

    $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'mesero@pinguin.test');
});

it('responde 422 con credenciales incorrectas', function () {
    waiter(['email' => 'mesero@pinguin.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'mesero@pinguin.test', 'password' => 'mala'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El correo o la contraseña son incorrectos.');
});

it('valida los campos obligatorios en español', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.')
        ->assertJsonPath('errors.password.0', 'El campo contraseña es obligatorio.');
});

it('responde 429 después de 5 intentos fallidos', function () {
    waiter(['email' => 'mesero@pinguin.test']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'mesero@pinguin.test', 'password' => 'mala']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'mesero@pinguin.test', 'password' => 'password'])
        ->assertStatus(429);
});

it('rechaza a un usuario desactivado', function () {
    User::factory()->waiter()->inactive()->create(['email' => 'mesero@pinguin.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'mesero@pinguin.test', 'password' => 'password'])
        ->assertUnprocessable();
});

it('responde 401 en español sin token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'No has iniciado sesión o tu sesión expiró.');
});

it('revoca el token al cerrar sesión', function () {
    $user = waiter();
    $token = $user->createToken('app')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('invalida el token de un usuario desactivado', function () {
    $user = waiter();
    $token = $user->createToken('app')->plainTextToken;
    $user->update(['active' => false]);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});
