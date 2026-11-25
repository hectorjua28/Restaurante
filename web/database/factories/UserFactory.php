<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Mesero,
            'active' => true,
        ];
    }

    public function waiter(): static
    {
        return $this->state(['role' => Role::Mesero]);
    }

    public function kitchen(): static
    {
        return $this->state(['role' => Role::Cocina]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
