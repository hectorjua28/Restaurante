<?php

namespace App\Http\Resources;

use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiningTable */
class TableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            /** Órdenes activas (pendiente, en preparación, lista o entregada). */
            'active_orders_count' => (int) ($this->active_orders_count ?? 0),
            'active_total_cents' => (int) ($this->active_orders_sum_total_cents ?? 0),
        ];
    }
}
