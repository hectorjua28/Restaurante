<?php

namespace App\Http\Resources;

use App\Models\Modifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Modifier */
class ModifierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_delta_cents' => $this->price_delta_cents,
        ];
    }
}
