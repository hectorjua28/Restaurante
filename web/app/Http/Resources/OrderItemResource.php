<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product_name,
            'station' => $this->station->value,
            'quantity' => $this->quantity,
            /** Precio unitario congelado al momento de la venta (incluye modificadores). */
            'unit_price_cents' => $this->unit_price_cents,
            'subtotal_cents' => $this->subtotal_cents,
            'modifiers' => array_map(fn (array $m) => [
                'id' => $m['id'],
                'name' => $m['name'],
                'price_delta_cents' => $m['price_delta_cents'],
            ], $this->modifiers ?? []),
            'note' => $this->note ?? '',
        ];
    }
}
