<?php

namespace App\Models;

use App\Enums\Station;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de la orden. Guarda nombre, precio y modificadores tal como estaban al vender.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property Station $station
 * @property int $quantity
 * @property int $unit_price_cents
 * @property array<int, array{id:int,name:string,price_delta_cents:int}>|null $modifiers
 * @property string|null $note
 * @property int $subtotal_cents
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'station', 'quantity',
        'unit_price_cents', 'modifiers', 'note', 'subtotal_cents',
    ];

    protected function casts(): array
    {
        return [
            'station' => Station::class,
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'modifiers' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
