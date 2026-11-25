<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->hasRole(Role::Cocina, Role::Admin) || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Mesero;
    }

    /** Solo el mesero que tomó la orden la marca como entregada. */
    public function deliver(User $user, Order $order): Response
    {
        return $user->role === Role::Mesero && $order->user_id === $user->id
            ? Response::allow()
            : Response::deny('Solo el mesero que tomó la orden puede marcarla como entregada.');
    }

    /** El mesero dueño de la orden o un administrador pueden cancelarla. */
    public function cancel(User $user, Order $order): Response
    {
        return $user->isAdmin() || ($user->role === Role::Mesero && $order->user_id === $user->id)
            ? Response::allow()
            : Response::deny('Solo el mesero que tomó la orden puede cancelarla.');
    }

    /** Cocina (y admin) avanzan la preparación. */
    public function advance(User $user, Order $order): bool
    {
        return $user->hasRole(Role::Cocina, Role::Admin);
    }
}
