<?php

namespace App\Http\Requests\Api;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Clave única generada por la app (UUID) para no duplicar la orden si se reenvía. */
            'idempotency_key' => ['required', 'uuid'],
            'table_id' => ['required', 'integer', Rule::exists('tables', 'id')],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.modifier_ids' => ['sometimes', 'array', 'max:10'],
            'items.*.modifier_ids.*' => ['integer', 'distinct'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<array{product_id:int, quantity:int, modifier_ids?:list<int>, note?:string|null}> */
    public function items(): array
    {
        /** @var list<array{product_id:int, quantity:int, modifier_ids?:list<int>, note?:string|null}> $items */
        $items = $this->validated('items');

        return $items;
    }
}
