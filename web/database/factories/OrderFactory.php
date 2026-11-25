<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_id' => DiningTable::factory(),
            'user_id' => User::factory()->waiter(),
            'status' => OrderStatus::Pendiente,
            'total_cents' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
