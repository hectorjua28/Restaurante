<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'price_cents' => $this->price_cents,
            'station' => $this->station->value,
            /** false = agotado: la app lo muestra deshabilitado. */
            'available' => $this->available,
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
        ];
    }
}
