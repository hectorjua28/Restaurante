<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Contraseña de todas las cuentas de prueba. */
    public const PASSWORD = 'pinguin123';

    public function run(): void
    {
        $users = [
            ['name' => 'Administración Pingüin', 'email' => 'admin@pinguin.test', 'role' => Role::Admin],
            ['name' => 'Cocina Pingüin', 'email' => 'cocina@pinguin.test', 'role' => Role::Cocina],
            ['name' => 'Luis Hernández', 'email' => 'mesero@pinguin.test', 'role' => Role::Mesero],
            ['name' => 'María Olmedo', 'email' => 'mesera@pinguin.test', 'role' => Role::Mesero],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + ['password' => self::PASSWORD, 'active' => true],
            );
        }
    }
}
