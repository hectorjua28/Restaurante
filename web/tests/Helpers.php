<?php

/*
 * Utilidades compartidas por las pruebas de la API.
 */

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function makeTable(int $number = 1): DiningTable
{
    return DiningTable::factory()->create(['number' => $number]);
}

function makeProduct(array $attributes = [], array $modifiers = []): Product
{
    $product = Product::factory()->for(Category::factory())->create($attributes);
    foreach ($modifiers as $name => $delta) {
        $product->modifiers()->create(['name' => $name, 'price_delta_cents' => $delta]);
    }

    return $product->load('modifiers');
}

/** Envía una comanda por la API y devuelve la respuesta. */
function sendOrder(DiningTable $table, array $items, ?string $key = null)
{
    return test()->postJson('/api/v1/orders', [
        'idempotency_key' => $key ?? (string) Str::uuid(),
        'table_id' => $table->id,
        'items' => $items,
    ]);
}

/** Crea una orden real (por la API) como el mesero dado y la devuelve. */
function placeOrder(User $waiter, DiningTable $table, ?Product $product = null, int $qty = 1): Order
{
    actingAsApi($waiter);
    $product ??= makeProduct(['price_cents' => 5000]);
    $id = sendOrder($table, [['product_id' => $product->id, 'quantity' => $qty]])->assertCreated()->json('data.id');

    return Order::findOrFail($id);
}

/** Lleva una orden hasta «entregada» por la API (cocina avanza dos veces, el mesero entrega). */
function deliverOrder(Order $order): Order
{
    actingAsApi(cook());
    test()->postJson("/api/v1/kitchen/orders/{$order->id}/advance")->assertOk();
    test()->postJson("/api/v1/kitchen/orders/{$order->id}/advance")->assertOk();

    actingAsApi(User::findOrFail($order->user_id));
    test()->postJson("/api/v1/orders/{$order->id}/deliver")->assertOk();

    return $order->refresh();
}

function modifierId(Product $product, string $name): int
{
    /** @var Modifier $m */
    $m = $product->modifiers->firstWhere('name', $name);

    return $m->id;
}
