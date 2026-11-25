<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;

describe('mesas', function () {
    it('lista las mesas con contadores por estado', function () {
        actingAsApi(waiter());
        makeTable(1);
        makeTable(2)->update(['status' => 'ocupada']);
        makeTable(3)->update(['status' => 'por_cobrar']);

        $this->getJson('/api/v1/tables')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.number', 1)
            ->assertJsonPath('data.0.status_label', 'Libre')
            ->assertJsonPath('meta.counts', ['total' => 3, 'libre' => 1, 'ocupada' => 1, 'por_cobrar' => 1]);
    });

    it('filtra las mesas por estado', function () {
        actingAsApi(waiter());
        makeTable(1);
        makeTable(2)->update(['status' => 'ocupada']);

        $this->getJson('/api/v1/tables?status=ocupada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('meta.counts.total', 2);

        $this->getJson('/api/v1/tables?status=rota')->assertUnprocessable();
    });

    it('muestra el detalle de una mesa con su total activo', function () {
        $waiter = waiter();
        $table = makeTable(5);
        placeOrder($waiter, $table, makeProduct(['price_cents' => 4500]), 2);

        $this->getJson("/api/v1/tables/{$table->id}")
            ->assertOk()
            ->assertJsonPath('data.number', 5)
            ->assertJsonPath('data.status', 'ocupada')
            ->assertJsonPath('data.active_orders_count', 1)
            ->assertJsonPath('data.active_total_cents', 9000);

        $this->getJson('/api/v1/tables/999')->assertNotFound()->assertJsonPath('message', 'El recurso solicitado no existe.');
    });
});

describe('menú', function () {
    it('lista las categorías activas en orden', function () {
        actingAsApi(waiter());
        Category::factory()->create(['name' => 'Bebidas', 'position' => 2]);
        Category::factory()->create(['name' => 'Antojitos', 'position' => 1]);
        Category::factory()->create(['name' => 'Oculta', 'position' => 0, 'active' => false]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Antojitos');
    });

    it('lista platillos con modificadores, incluyendo los agotados', function () {
        actingAsApi(waiter());
        makeProduct(['name' => 'Bocoles', 'price_cents' => 4500], ['Con queso' => 1000]);
        makeProduct(['name' => 'Zacahuil', 'available' => false]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Bocoles')
            ->assertJsonPath('data.0.price_cents', 4500)
            ->assertJsonPath('data.0.modifiers.0.name', 'Con queso')
            ->assertJsonPath('data.0.modifiers.0.price_delta_cents', 1000)
            ->assertJsonPath('data.1.available', false);
    });

    it('busca platillos por nombre y filtra por categoría', function () {
        actingAsApi(waiter());
        $bocoles = makeProduct(['name' => 'Bocoles con frijol']);
        makeProduct(['name' => 'Café de olla']);

        $this->getJson('/api/v1/products?search=bocol')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bocoles con frijol');
        $this->getJson("/api/v1/products?category_id={$bocoles->category_id}")->assertJsonCount(1, 'data');
    });

    it('no muestra platillos eliminados', function () {
        actingAsApi(waiter());
        makeProduct()->delete();

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('permisos por rol', function () {
    it('responde 401 sin token en todas las rutas protegidas', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized();
    })->with([
        ['GET', '/api/v1/tables'],
        ['GET', '/api/v1/products'],
        ['POST', '/api/v1/orders'],
        ['GET', '/api/v1/kitchen/orders'],
        ['POST', '/api/v1/tables/1/payments'],
    ]);

    it('solo meseros usan las rutas de la app', function (string $method, string $uri) {
        actingAsApi(cook());
        $this->json($method, $uri)->assertForbidden();

        actingAsApi(admin());
        $this->json($method, $uri)->assertForbidden();
    })->with([
        ['GET', '/api/v1/tables'],
        ['GET', '/api/v1/tables/1/bill'],
        ['POST', '/api/v1/tables/1/payments'],
        ['GET', '/api/v1/categories'],
        ['GET', '/api/v1/products'],
        ['GET', '/api/v1/orders'],
        ['POST', '/api/v1/orders'],
        ['POST', '/api/v1/orders/1/deliver'],
        ['POST', '/api/v1/orders/1/cancel'],
    ]);

    it('los meseros no usan las rutas de cocina', function (string $method, string $uri) {
        actingAsApi(waiter());
        $this->json($method, $uri)->assertForbidden();
    })->with([
        ['GET', '/api/v1/kitchen/orders'],
        ['POST', '/api/v1/kitchen/orders/1/advance'],
        ['PATCH', '/api/v1/products/1/availability'],
    ]);

    it('un mesero no ve las órdenes de otro mesero', function () {
        $order = placeOrder(waiter(), makeTable());

        actingAsApi(waiter());
        $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
    });
});

it('cocina ve las órdenes activas en orden de llegada y filtra por estación', function () {
    $waiter = waiter();
    $food = makeProduct(['station' => 'cocina']);
    $drink = makeProduct(['station' => 'barra']);

    $this->travel(-10)->minutes();
    $first = placeOrder($waiter, makeTable(1), $food);
    $this->travelBack();
    $second = placeOrder($waiter, makeTable(2), $drink);
    $done = placeOrder($waiter, makeTable(3), $food);
    $done->update(['status' => OrderStatus::Cancelada]);

    actingAsApi(cook());
    $this->getJson('/api/v1/kitchen/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id);

    $this->getJson('/api/v1/kitchen/orders?station=barra')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.items.0.station', 'barra');

    expect(Order::count())->toBe(3);
});
