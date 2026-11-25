<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('crea un administrador con contraseña segura', function () {
    $this->artisan('pinguin:crear-admin', ['email' => 'Dueno@Pinguin.test', 'name' => 'Dueño Pingüin'])
        ->expectsQuestion('Contraseña (mínimo 10 caracteres, con letras y números)', 'Vainilla2026')
        ->expectsQuestion('Repite la contraseña', 'Vainilla2026')
        ->expectsOutput('Administrador listo: dueno@pinguin.test')
        ->assertSuccessful();

    $user = User::where('email', 'dueno@pinguin.test')->sole();
    expect($user->role)->toBe(Role::Admin)
        ->and(Hash::check('Vainilla2026', $user->password))->toBeTrue();
});

it('rechaza contraseñas débiles o que no coinciden', function () {
    $this->artisan('pinguin:crear-admin', ['email' => 'x@pinguin.test', 'name' => 'X'])
        ->expectsQuestion('Contraseña (mínimo 10 caracteres, con letras y números)', 'corta')
        ->expectsQuestion('Repite la contraseña', 'otra')
        ->assertFailed();

    expect(User::where('email', 'x@pinguin.test')->exists())->toBeFalse();
});
