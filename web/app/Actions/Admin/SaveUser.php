<?php

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Alta y edición de usuarios. Al desactivar a alguien se revocan sus tokens de la app.
 * Un administrador no puede desactivarse ni quitarse el rol a sí mismo.
 */
class SaveUser
{
    /** @param array{name:string, email:string, role:string, active:bool, password?:string|null} $data */
    public function handle(?User $user, array $data, User $by): User
    {
        $password = $data['password'] ?? null;
        unset($data['password']);

        if ($user !== null && $user->is($by)) {
            if (! $data['active']) {
                throw new BusinessRuleException('No puedes desactivar tu propia cuenta.');
            }
            if ($data['role'] !== Role::Admin->value) {
                throw new BusinessRuleException('No puedes quitarte el rol de administrador.');
            }
        }

        return DB::transaction(function () use ($user, $data, $password, $by) {
            $isNew = $user === null;
            $user ??= new User;
            $user->fill($data);
            $user->email = mb_strtolower(trim($data['email']));
            if ($password !== null && $password !== '') {
                $user->password = $password;
            }
            $user->save();

            if ($isNew) {
                Audit::log('user.created', $user, Audit::attributes($user), $by->id);
            } elseif (($changes = Audit::changes($user)) !== []) {
                Audit::log('user.updated', $user, ['cambios' => $changes], $by->id);
            }

            if (! $user->active) {
                $user->tokens()->delete();
            }

            return $user;
        });
    }
}
