<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'table' => [
                'id' => $this->table_id,
                'number' => $this->diningTable->number,
            ],
            'waiter' => [
                'id' => $this->user_id,
                'name' => $this->waiter->name,
            ],
            'total_cents' => $this->total_cents,
            'cancel_reason' => $this->cancel_reason ?? '',
            'idempotency_key' => $this->idempotency_key,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            /** Fechas en UTC (ISO 8601); la app las convierte a America/Mexico_City. */
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
