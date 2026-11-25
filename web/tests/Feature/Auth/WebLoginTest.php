<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('cocina@pinguin.test|127.0.0.1'));

it('muestra la pantalla de inicio de sesión', function () {
    $this->get('/login')->assertOk()->assertSee('Iniciar sesión', false)->assertSee('Entrar');
});

it('redirige la raíz al login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('redirige a cocina al tablero de cocina', function () {
    cook(['email' => 'cocina@pinguin.test']);

    $this->post('/login', ['email' => 'cocina@pinguin.test', 'password' => 'password'])
        ->assertRedirect(route('kitchen.board'));

    $this->assertAuthenticated();
});

it('redirige al administrador al panel de administración', function () {
    admin(['email' => 'admin@pinguin.test']);

    $this->post('/login', ['email' => 'admin@pinguin.test', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});

it('no deja entrar a meseros al sitio web', function () {
    waiter(['email' => 'mesero@pinguin.test']);

    $this->post('/login', ['email' => 'mesero@pinguin.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rechaza una contraseña incorrecta', function () {
    cook(['email' => 'cocina@pinguin.test']);

    $this->post('/login', ['email' => 'cocina@pinguin.test', 'password' => 'mala'])
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);

    $this->assertGuest();
});

it('rechaza a un usuario desactivado', function () {
    User::factory()->kitchen()->inactive()->create(['email' => 'cocina@pinguin.test']);

    $this->post('/login', ['email' => 'cocina@pinguin.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloquea después de 5 intentos fallidos', function () {
    cook(['email' => 'cocina@pinguin.test']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => 'cocina@pinguin.test', 'password' => 'mala']);
    }

    $this->post('/login', ['email' => 'cocina@pinguin.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Demasiados intentos');
    $this->assertGuest();
});

it('cierra la sesión', function () {
    $this->actingAs(cook())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('protege los paneles por rol', function () {
    $this->get('/cocina')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');

    $this->actingAs(cook())->get('/admin')->assertForbidden();
    $this->actingAs(waiter())->get('/cocina')->assertForbidden();
    $this->actingAs(waiter())->get('/admin')->assertForbidden();

    $this->actingAs(cook())->get('/cocina')->assertOk();
    $this->actingAs(admin())->get('/admin')->assertOk();
    $this->actingAs(admin())->get('/cocina')->assertOk();
});

it('saca de la sesión a un usuario desactivado', function () {
    $user = cook();
    $this->actingAs($user);
    $user->update(['active' => false]);

    $this->get('/cocina')->assertRedirect('/login');
});

it('redirige a su panel a un usuario que ya inició sesión', function () {
    $this->actingAs(cook())->get('/login')->assertRedirect(route('kitchen.board'));
});
