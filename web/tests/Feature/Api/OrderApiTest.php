<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Str;

describe('crear comanda', function () {
    it('calcula el total en el servidor con modificadores y cantidades', function () {
        $waiter = actingAsApi(waiter());
        $table = makeTable(4);
        $bocoles = makeProduct(['name' => 'Bocoles', 'price_cents' => 4500], ['Con queso' => 1000, 'Sin salsa' => 0]);
        $cafe = makeProduct(['name' => 'Café de olla', 'price_cents' => 3000, 'station' => 'barra']);

        $response = sendOrder($table, [
            [
                'product_id' => $bocoles->id,
                'quantity' => 2,
                'modifier_ids' => [modifierId($bocoles, 'Con queso'), modifierId($bocoles, 'Sin salsa')],
                'note' => '  Bien doraditos  ',
                // Un precio enviado por el cliente se ignora.
                'price_cents' => 1,
            ],
            ['product_id' => $cafe->id, 'quantity' => 1],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.status_label', 'Pendiente')
            ->assertJsonPath('data.table.number', 4)
            ->assertJsonPath('data.waiter.id', $waiter->id)
            ->assertJsonPath('data.total_cents', 2 * 5500 + 3000)
            ->assertJsonPath('data.items.0.unit_price_cents', 5500)
            ->assertJsonPath('data.items.0.subtotal_cents', 11000)
            ->assertJsonPath('data.items.0.note', 'Bien doraditos')
            ->assertJsonPath('data.items.0.modifiers.0.name', 'Con queso')
            ->assertJsonPath('data.items.1.station', 'barra')
            ->assertJsonPath('data.items.1.note', '');

        expect($response->json('data.created_at'))->toEndWith('Z');
    });

    it('guarda el precio al momento de la venta aunque después cambie', function () {
        $waiter = waiter();
        $product = makeProduct(['price_cents' => 4500]);
        $order = placeOrder($waiter, makeTable(), $product, 2);

        $product->update(['price_cents' => 9900, 'name' => 'Nombre nuevo']);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('data.total_cents', 9000)
            ->assertJsonPath('data.items.0.unit_price_cents', 4500)
            ->assertJsonPath('data.items.0.name', $order->items()->first()->product_name);
    });

    it('pone la mesa como ocupada y registra el historial', function () {
        $table = makeTable();
        $order = placeOrder(waiter(), $table);

        expect($table->refresh()->status->value)->toBe('ocupada')
            ->and($order->statusHistory()->count())->toBe(1)
            ->and($order->statusHistory()->first()->to_status)->toBe(OrderStatus::Pendiente)
            ->and(AuditLog::where('action', 'order.created')->count())->toBe(1);
    });

    it('rechaza platillos agotados', function () {
        actingAsApi(waiter());
        $table = makeTable();
        $soldOut = makeProduct(['name' => 'Zacahuil', 'available' => false]);

        sendOrder($table, [['product_id' => $soldOut->id, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonPath('message', '«Zacahuil» está agotado.')
            ->assertJsonPath('code', 'product_sold_out');

        expect(Order::count())->toBe(0)
            ->and($table->refresh()->status->value)->toBe('libre');
    });

    it('marcar agotado no afecta las órdenes ya enviadas', function () {
        $waiter = waiter();
        $product = makeProduct();
        $order = placeOrder($waiter, makeTable(), $product);

        actingAsApi(cook());
        $this->patchJson("/api/v1/products/{$product->id}/availability", ['available' => false])->assertOk();
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance")->assertOk()->assertJsonPath('data.status', 'en_preparacion');

        expect($order->refresh()->items()->count())->toBe(1);
    });

    it('rechaza platillos eliminados o inexistentes', function () {
        actingAsApi(waiter());
        $product = makeProduct();
        $product->delete();

        sendOrder(makeTable(), [['product_id' => $product->id, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'product_missing');
    });

    it('rechaza modificadores de otro platillo', function () {
        actingAsApi(waiter());
        $a = makeProduct([], ['Con queso' => 1000]);
        $b = makeProduct();

        sendOrder(makeTable(), [['product_id' => $b->id, 'quantity' => 1, 'modifier_ids' => [modifierId($a, 'Con queso')]]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_modifier');
    });

    it('valida la estructura de la comanda', function (array $payload, string $field) {
        actingAsApi(waiter());
        $table = makeTable();

        $this->postJson('/api/v1/orders', array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'table_id' => $table->id,
            'items' => [['product_id' => makeProduct()->id, 'quantity' => 1]],
        ], $payload))->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'sin platillos' => [['items' => []], 'items'],
        'cantidad cero' => [['items' => [['product_id' => 1, 'quantity' => 0]]], 'items.0.quantity'],
        'mesa inexistente' => [['table_id' => 999], 'table_id'],
        'sin clave' => [['idempotency_key' => null], 'idempotency_key'],
        'clave inválida' => [['idempotency_key' => 'abc'], 'idempotency_key'],
        'nota muy larga' => [['items' => [['product_id' => 1, 'quantity' => 1, 'note' => str_repeat('a', 256)]]], 'items.0.note'],
    ]);
});

describe('idempotencia', function () {
    it('devuelve la misma orden si llega dos veces la misma clave', function () {
        actingAsApi(waiter());
        $table = makeTable();
        $product = makeProduct(['price_cents' => 3000]);
        $key = (string) Str::uuid();
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $first = sendOrder($table, $items, $key)->assertCreated();
        $second = sendOrder($table, $items, $key)->assertOk();

        expect($second->json('data.id'))->toBe($first->json('data.id'))
            ->and(Order::count())->toBe(1)
            ->and(OrderStatusHistory::count())->toBe(1);
    });

    it('no deja reutilizar la clave de otro mesero', function () {
        actingAsApi(waiter());
        $table = makeTable();
        $product = makeProduct();
        $key = (string) Str::uuid();
        sendOrder($table, [['product_id' => $product->id, 'quantity' => 1]], $key)->assertCreated();

        actingAsApi(waiter());
        sendOrder($table, [['product_id' => $product->id, 'quantity' => 1]], $key)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_conflict');
    });
});

describe('flujo de estados', function () {
    it('cocina avanza pendiente → en preparación → lista y no puede entregar', function () {
        $order = placeOrder(waiter(), makeTable());

        actingAsApi(cook());
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance")->assertOk()->assertJsonPath('data.status', 'en_preparacion');
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance")->assertOk()->assertJsonPath('data.status', 'lista');
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La orden ya está lista; ahora la entrega el mesero.');

        expect($order->statusHistory()->pluck('to_status')->all())->toBe([
            OrderStatus::Pendiente, OrderStatus::EnPreparacion, OrderStatus::Lista,
        ]);
    });

    it('registra quién y desde qué estado cambió cada paso', function () {
        $order = placeOrder(waiter(), makeTable());
        $cook = actingAsApi(cook());
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance");

        $last = $order->statusHistory()->get()->last();
        expect($last->from_status)->toBe(OrderStatus::Pendiente)
            ->and($last->to_status)->toBe(OrderStatus::EnPreparacion)
            ->and($last->user_id)->toBe($cook->id);
    });

    it('el mesero solo entrega órdenes listas', function () {
        $waiter = waiter();
        $order = placeOrder($waiter, makeTable());

        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/deliver")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede pasar de «Pendiente» a «Entregada».');

        $delivered = deliverOrder($order);
        expect($delivered->status)->toBe(OrderStatus::Entregada);
    });

    it('solo el mesero dueño entrega su orden', function () {
        $order = placeOrder(waiter(), makeTable());
        actingAsApi(cook());
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance");
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance");

        actingAsApi(waiter());
        $this->postJson("/api/v1/orders/{$order->id}/deliver")
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el mesero que tomó la orden puede marcarla como entregada.');
    });

    it('la mesa pasa a «por cobrar» cuando todas sus órdenes están entregadas', function () {
        $waiter = waiter();
        $table = makeTable();
        $a = placeOrder($waiter, $table);
        $b = placeOrder($waiter, $table);

        deliverOrder($a);
        expect($table->refresh()->status->value)->toBe('ocupada');

        deliverOrder($b);
        expect($table->refresh()->status->value)->toBe('por_cobrar');
    });

    it('lista mis órdenes activas y filtra por estado', function () {
        $waiter = waiter();
        $table = makeTable();
        $a = placeOrder($waiter, $table);
        placeOrder($waiter, $table);
        deliverOrder($a);

        actingAsApi($waiter);
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/orders?status=entregada')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);
        $this->getJson('/api/v1/orders?status=pendiente,lista')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/orders?status=inventado')->assertUnprocessable();
    });

    it('muestra el detalle con el historial de estados', function () {
        $waiter = waiter();
        $order = deliverOrder(placeOrder($waiter, makeTable()));

        actingAsApi($waiter);
        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.history')
            ->assertJsonPath('data.history.0.to_status', 'pendiente')
            ->assertJsonPath('data.history.3.to_status_label', 'Entregada')
            ->assertJsonPath('data.history.0.user_name', $waiter->name);
    });
});

describe('cancelación', function () {
    it('el mesero cancela una orden pendiente con motivo y queda en la bitácora', function () {
        $waiter = waiter();
        $table = makeTable();
        $order = placeOrder($waiter, $table);

        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'El cliente se fue'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelada')
            ->assertJsonPath('data.cancel_reason', 'El cliente se fue');

        $log = AuditLog::where('action', 'order.cancelled')->sole();
        expect($log->auditable_id)->toBe($order->id)
            ->and($log->user_id)->toBe($waiter->id)
            ->and($log->data['reason'])->toBe('El cliente se fue')
            ->and($table->refresh()->status->value)->toBe('libre')
            ->and($order->statusHistory()->get()->last()->to_status)->toBe(OrderStatus::Cancelada);
    });

    it('exige el motivo', function () {
        $waiter = waiter();
        $order = placeOrder($waiter, makeTable());

        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'El campo motivo es obligatorio.');
    });

    it('no cancela órdenes que ya están en preparación', function () {
        $waiter = waiter();
        $order = placeOrder($waiter, makeTable());
        actingAsApi(cook());
        $this->postJson("/api/v1/kitchen/orders/{$order->id}/advance");

        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Ya no quiere'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_transition');

        expect($order->refresh()->status)->toBe(OrderStatus::EnPreparacion);
    });

    it('un mesero no cancela órdenes de otro', function () {
        $order = placeOrder(waiter(), makeTable());

        actingAsApi(waiter());
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Prueba'])->assertForbidden();
    });

    it('la mesa sigue ocupada si tiene otras órdenes', function () {
        $waiter = waiter();
        $table = makeTable();
        $order = placeOrder($waiter, $table);
        placeOrder($waiter, $table);

        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Duplicada']);

        expect($table->refresh()->status->value)->toBe('ocupada');
    });
});

describe('platillos agotados', function () {
    it('cocina marca un platillo agotado y disponible, con bitácora', function () {
        $product = makeProduct(['name' => 'Bocoles']);
        $cook = actingAsApi(cook());

        $this->patchJson("/api/v1/products/{$product->id}/availability", ['available' => false])
            ->assertOk()
            ->assertJsonPath('data.available', false);
        $this->patchJson("/api/v1/products/{$product->id}/availability", ['available' => true])
            ->assertJsonPath('data.available', true);

        expect(AuditLog::where('action', 'product.sold_out')->where('user_id', $cook->id)->count())->toBe(1)
            ->and(AuditLog::where('action', 'product.available')->count())->toBe(1);
    });

    it('valida el campo disponible', function () {
        actingAsApi(cook());
        $this->patchJson('/api/v1/products/'.makeProduct()->id.'/availability', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('available');
    });
});
