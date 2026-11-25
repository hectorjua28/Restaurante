<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

function waiter(array $attributes = []): User
{
    return User::factory()->waiter()->create($attributes);
}

function cook(array $attributes = []): User
{
    return User::factory()->kitchen()->create($attributes);
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

/** Autentica al usuario en la API (Sanctum) y lo devuelve. */
function actingAsApi(User $user): User
{
    Sanctum::actingAs($user);

    return $user;
}

require_once __DIR__.'/Helpers.php';
