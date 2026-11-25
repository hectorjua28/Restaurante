<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DiningTable $table): bool
    {
        return true;
    }

    /** Cualquier mesero puede cobrar cualquier mesa (las mesas son compartidas). */
    public function charge(User $user, DiningTable $table): bool
    {
        return $user->role === Role::Mesero;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DiningTable $table): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DiningTable $table): bool
    {
        return $user->isAdmin();
    }
}
