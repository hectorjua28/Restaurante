<?php

use App\Enums\OrderStatus;
use App\Livewire\Kitchen\Availability;
use App\Livewire\Kitchen\Board;
use App\Models\AuditLog;
use App\Models\Order;
use Livewire\Livewire;

/** Orden real creada por la API para la mesa indicada. */
function kitchenOrder(int $tableNumber, array $productAttrs = [], int $qty = 1, array $extra = []): Order
{
    $product = makeProduct($productAttrs, $extra['modifiers'] ?? []);
    actingAsApi($waiter = waiter(['name' => $extra['waiter'] ?? 'Luis']));
    $items = [[
        'product_id' => $product->id,
        'quantity' => $qty,
        'modifier_ids' => $product->modifiers->pluck('id')->all(),
        'note' => $extra['note'] ?? null,
    ]];
    $id = sendOrder(makeTable($tableNumber), $items)->assertCreated()->json('data.id');

    return Order::findOrFail($id);
}

it('solo cocina y administración entran al panel de cocina', function () {
    $this->get('/cocina')->assertRedirect('/login');
    $this->actingAs(waiter())->get('/cocina')->assertForbidden();
    $this->actingAs(waiter())->get('/cocina/agotados')->assertForbidden();

    $this->actingAs(cook())->get('/cocina')->assertOk()->assertSeeLivewire(Board::class);
    $this->actingAs(cook())->get('/cocina/agotados')->assertOk()->assertSeeLivewire(Availability::class);
});

it('muestra el tablero en tres columnas, en orden de llegada, con todos los datos de la tarjeta', function () {
    $this->travelTo(now()->setTime(18, 0));
    $first = kitchenOrder(3, ['name' => 'Bocoles'], 2, [
        'waiter' => 'María Olmedo',
        'note' => 'Sin cebolla',
        'modifiers' => ['Con queso' => 1000],
    ]);
    $this->travel(5)->minutes();
    $second = kitchenOrder(5, ['name' => 'Café de olla', 'station' => 'barra']);
    $this->travel(12)->minutes();

    $this->actingAs(cook());
    Livewire::test(Board::class)
        ->assertSeeInOrder(['Pendiente', 'Mesa 3', 'Mesa 5', 'En preparación', 'Lista'])
        ->assertSee('#'.$first->id)
        ->assertSee('María Olmedo')
        ->assertSee('2×')
        ->assertSee('Bocoles')
        ->assertSee('Con queso')
        ->assertSee('Nota: Sin cebolla')
        ->assertSee('12:00') // 18:00 UTC = 12:00 en Ciudad de México
        ->assertSee('17 min')
        ->assertSee('12 min')
        ->assertSee('Empezar a preparar')
        ->assertDontSee('undefined')
        ->assertDontSee('NaN');

    expect($second->status)->toBe(OrderStatus::Pendiente);
});

it('avanza una orden a en preparación y luego a lista, registrando el historial', function () {
    $order = kitchenOrder(2);
    $cook = cook();
    $this->actingAs($cook);

    $board = Livewire::test(Board::class)
        ->call('advance', $order->id)
        ->assertSet('flashType', 'success')
        ->assertSee('Marcar como lista');
    expect($order->refresh()->status)->toBe(OrderStatus::EnPreparacion);

    $board->call('advance', $order->id)->assertSee('Esperando a que el mesero la entregue');
    expect($order->refresh()->status)->toBe(OrderStatus::Lista)
        ->and($order->statusHistory()->get()->last()->user_id)->toBe($cook->id);

    // Cocina no puede entregar: muestra el aviso y no cambia nada.
    $board->call('advance', $order->id)
        ->assertSet('flashType', 'error')
        ->assertSet('flash', 'La orden ya está lista; ahora la entrega el mesero.');
    expect($order->refresh()->status)->toBe(OrderStatus::Lista);
});

it('avisa si otra persona de cocina ya avanzó la orden', function () {
    $order = kitchenOrder(2);
    $this->actingAs(cook());
    $board = Livewire::test(Board::class);

    // Otra persona la cancela/avanza mientras tanto.
    $order->update(['status' => OrderStatus::Cancelada]);

    $board->call('advance', $order->id)
        ->assertSet('flashType', 'error')
        ->assertSet('flash', 'La orden está cancelada y no se puede modificar.');
});

it('no muestra órdenes entregadas, cobradas ni canceladas', function () {
    $order = kitchenOrder(9);
    $order->update(['status' => OrderStatus::Entregada]);

    $this->actingAs(cook());
    Livewire::test(Board::class)->assertDontSee('Mesa 9');
});

it('filtra por estación', function () {
    kitchenOrder(1, ['name' => 'Enchiladas verdes', 'station' => 'cocina']);
    kitchenOrder(2, ['name' => 'Agua de jamaica', 'station' => 'barra']);

    $this->actingAs(cook());
    Livewire::test(Board::class)
        ->call('setStation', 'barra')
        ->assertSee('Agua de jamaica')
        ->assertDontSee('Enchiladas verdes')
        ->call('setStation', 'cocina')
        ->assertSee('Enchiladas verdes')
        ->assertDontSee('Agua de jamaica')
        ->call('setStation', 'inventada')
        ->assertSet('station', '')
        ->assertSee('Agua de jamaica');
});

it('se actualiza solo con polling', function () {
    $this->actingAs(cook());
    $this->get('/cocina')->assertSee('wire:poll.4s', false)->assertSee('offline-banner', false);
});

describe('platillos agotados', function () {
    it('cocina marca un platillo como agotado y otra vez disponible', function () {
        $product = makeProduct(['name' => 'Zacahuil']);
        $cook = cook();
        $this->actingAs($cook);

        Livewire::test(Availability::class)
            ->assertSee('Zacahuil')
            ->call('toggle', $product->id)
            ->assertSee('«Zacahuil» marcado como agotado.')
            ->assertSee('1 agotado');
        expect($product->refresh()->available)->toBeFalse();

        Livewire::test(Availability::class)->call('toggle', $product->id);
        expect($product->refresh()->available)->toBeTrue()
            ->and(AuditLog::where('auditable_id', $product->id)->pluck('action')->all())
            ->toBe(['product.sold_out', 'product.available']);
    });

    it('busca y filtra solo agotados', function () {
        makeProduct(['name' => 'Bocoles']);
        makeProduct(['name' => 'Picadas', 'available' => false]);

        $this->actingAs(cook());
        Livewire::test(Availability::class)
            ->set('search', 'boc')
            ->assertSee('Bocoles')
            ->assertDontSee('Picadas')
            ->set('search', '')
            ->set('onlySoldOut', true)
            ->assertSee('Picadas')
            ->assertDontSee('Bocoles');
    });
});
