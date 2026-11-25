<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mesa del restaurante. Se llama DiningTable para no chocar con la propiedad $table de Eloquent.
 *
 * @property int $id
 * @property int $number
 * @property int $capacity
 * @property TableStatus $status
 */
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $table = 'tables';

    protected $fillable = ['number', 'capacity', 'status'];

    protected $attributes = ['status' => 'libre'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'capacity' => 'integer',
            'status' => TableStatus::class,
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /** @return HasMany<Order, $this> */
    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', OrderStatus::active());
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'table_id');
    }
}
