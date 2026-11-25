<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderStatusHistory */
class OrderStatusHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'to_status_label' => $this->to_status->label(),
            'user_name' => $this->user->name ?? 'Sistema',
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
