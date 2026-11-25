<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_id' => $this->table_id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount_cents' => $this->amount_cents,
            'tip_cents' => $this->tip_cents,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
