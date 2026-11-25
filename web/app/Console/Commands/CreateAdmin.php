<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea (o reactiva) un administrador en producción sin usar contraseñas de prueba.
 * Uso: php artisan pinguin:crear-admin admin@tu-dominio.com "Nombre Apellido"
 */
class CreateAdmin extends Command
{
    protected $signature = 'pinguin:crear-admin {email : Correo del administrador} {name : Nombre completo}';

    protected $description = 'Crea un usuario administrador pidiendo su contraseña de forma segura';

    public function handle(): int
    {
        $password = (string) $this->secret('Contraseña (mínimo 10 caracteres, con letras y números)');
        $confirm = (string) $this->secret('Repite la contraseña');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password, 'password_confirmation' => $confirm],
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:100'],
                'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => mb_strtolower($this->argument('email'))],
            ['name' => $this->argument('name'), 'password' => $password, 'role' => Role::Admin, 'active' => true],
        );
        Audit::log($user->wasRecentlyCreated ? 'user.created' : 'user.updated', $user, ['origen' => 'consola', 'rol' => 'admin'], $user->id);

        $this->info("Administrador listo: {$user->email}");

        return self::SUCCESS;
    }
}
