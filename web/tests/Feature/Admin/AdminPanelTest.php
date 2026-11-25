<?php

use App\Enums\OrderStatus;
use App\Livewire\Admin\Categories;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrderShow;
use App\Livewire\Admin\Payments;
use App\Livewire\Admin\Products\Form as ProductForm;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\Tables;
use App\Livewire\Admin\Users;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/** Mesa con una orden cobrada (pago en efectivo con propina) hecha por la API. */
function paidOrder(int $tableNumber = 1, int $priceCents = 10000, string $method = 'efectivo', string $tip = '0'): Order
{
    $waiter = waiter(['name' => 'Luis Hernández']);
    $table = makeTable($tableNumber);
    $order = deliverOrder(placeOrder($waiter, $table, makeProduct(['price_cents' => $priceCents])));
    actingAsApi($waiter);
    test()->postJson("/api/v1/tables/{$table->id}/payments", [
        'payments' => [['method' => $method, 'amount' => number_format($priceCents / 100, 2, '.', ''), 'tip' => $tip]],
    ])->assertCreated();

    return $order->refresh();
}

it('solo el administrador entra al panel de administración', function (string $uri) {
    $this->actingAs(cook())->get($uri)->assertForbidden();
    $this->actingAs(waiter())->get($uri)->assertForbidden();
    $this->actingAs(admin())->get($uri)->assertOk();
})->with(['/admin', '/admin/ordenes', '/admin/pagos', '/admin/platillos', '/admin/platillos/nuevo', '/admin/categorias', '/admin/mesas', '/admin/usuarios']);

describe('resumen', function () {
    it('muestra recaudado del día, órdenes activas y cerradas, ocupación y últimos movimientos', function () {
        paidOrder(1, 12000, 'tarjeta', '15');
        placeOrder(waiter(), makeTable(2));
        makeTable(3);

        $this->actingAs(admin());
        Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-testid="collected">$120.00')
            ->assertSee('propinas $15.00')
            ->assertSeeHtml('data-testid="active-orders">1')
            ->assertSeeHtml('data-testid="closed-orders">1')
            ->assertSeeHtml('data-testid="occupancy">1 / 3')
            ->assertSee('Últimas órdenes')
            ->assertSee('Tarjeta')
            ->assertDontSee('undefined')
            ->assertDontSee('NaN');
    });

    it('cuenta cerradas y canceladas por su historial, no por la última modificación', function () {
        $this->travel(-2)->days();
        $old = placeOrder($waiter = waiter(), makeTable(1));
        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$old->id}/cancel", ['reason' => 'Prueba'])->assertOk();
        $this->travelBack();
        $old->touch(); // modificación de hoy que no es un cambio de estado

        $this->actingAs(admin());
        Livewire::test(Dashboard::class)->assertSee('0 canceladas');
    });

    it('no cuenta pagos de días anteriores en el recaudado de hoy', function () {
        $this->travel(-2)->days();
        paidOrder(1, 50000);
        $this->travelBack();

        $this->actingAs(admin());
        Livewire::test(Dashboard::class)->assertSeeHtml('data-testid="collected">$0.00');
    });
});

describe('platillos', function () {
    it('crea un platillo con modificadores y precios en centavos', function () {
        $category = Category::factory()->create(['name' => 'Antojitos']);
        $admin = admin();
        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Bocoles con queso')
            ->set('categoryId', (string) $category->id)
            ->set('price', '52.50')
            ->set('station', 'cocina')
            ->call('addModifier')
            ->set('modifiers.0.name', 'Extra queso')
            ->set('modifiers.0.price', '12')
            ->call('addModifier')
            ->set('modifiers.1.name', 'Sin salsa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Bocoles con queso')->with('modifiers')->sole();
        expect($product->price_cents)->toBe(5250)
            ->and($product->modifiers->pluck('price_delta_cents', 'name')->all())->toBe(['Extra queso' => 1200, 'Sin salsa' => 0])
            ->and(AuditLog::where('action', 'product.created')->where('user_id', $admin->id)->exists())->toBeTrue();
    });

    it('valida nombre, precio y modificadores repetidos', function () {
        $this->actingAs(admin());
        Category::factory()->create();

        Livewire::test(ProductForm::class)
            ->set('name', '')
            ->set('price', '12.345')
            ->call('addModifier')->set('modifiers.0.name', 'Sin cebolla')
            ->call('addModifier')->set('modifiers.1.name', 'sin cebolla')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'price' => 'regex', 'modifiers.1.name']);
    });

    it('edita un platillo, registra los cambios en la bitácora y no altera órdenes anteriores', function () {
        $product = makeProduct(['name' => 'Picadas', 'price_cents' => 6000], ['Con huevo' => 1500, 'Sin cebolla' => 0]);
        $order = placeOrder(waiter(), makeTable(), $product, 2);
        $this->actingAs(admin());

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('price', '60.00')
            ->assertSet('modifiers.0.name', 'Con huevo')
            ->set('price', '65')
            ->call('removeModifier', 1)
            ->call('addModifier')->set('modifiers.1.name', 'Salsa verde')
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh()->load('modifiers');
        expect($product->price_cents)->toBe(6500)
            ->and($product->modifiers->pluck('name')->sort()->values()->all())->toBe(['Con huevo', 'Salsa verde'])
            ->and($order->refresh()->total_cents)->toBe(12000);

        $log = AuditLog::where('action', 'product.updated')->sole();
        expect($log->data['cambios']['price_cents'])->toBe(['antes' => 6000, 'después' => 6500])
            ->and($log->data['modificadores'])->toContain('eliminado: Sin cebolla', 'agregado: Salsa verde');
    });

    it('elimina (soft delete), filtra y cambia disponibilidad desde el listado', function () {
        $bocoles = makeProduct(['name' => 'Bocoles']);
        makeProduct(['name' => 'Café de olla']);
        $this->actingAs(admin());

        Livewire::test(ProductsIndex::class)
            ->set('search', 'boc')
            ->assertSee('Bocoles')
            ->assertDontSee('Café de olla')
            ->call('toggle', $bocoles->id)
            ->call('delete', $bocoles->id)
            ->assertSee('«Bocoles» se eliminó del menú.');

        expect(Product::find($bocoles->id))->toBeNull()
            ->and(Product::withTrashed()->find($bocoles->id)->available)->toBeFalse()
            ->and(AuditLog::where('action', 'product.deleted')->exists())->toBeTrue();
    });
});

describe('categorías y mesas', function () {
    it('crea, edita y no borra categorías con platillos', function () {
        $this->actingAs(admin());
        $used = makeProduct()->category;

        Livewire::test(Categories::class)
            ->call('create')
            ->set('name', 'Postres')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Categoría «Postres» guardada.')
            ->set('name', 'Postres')
            ->call('save')
            ->assertHasErrors(['name' => 'unique'])
            ->call('delete', $used->id)
            ->assertSet('flashType', 'error')
            ->assertSee('porque tiene platillos');

        $postres = Category::where('name', 'Postres')->sole();
        Livewire::test(Categories::class)->call('edit', $postres->id)->set('active', false)->call('save');

        expect($postres->refresh()->active)->toBeFalse()
            ->and(Category::find($used->id))->not->toBeNull()
            ->and(AuditLog::where('action', 'category.updated')->exists())->toBeTrue();
    });

    it('administra mesas con número único y no borra mesas con historial', function () {
        $this->actingAs(admin());
        $paid = paidOrder(5)->diningTable;

        Livewire::test(Tables::class)
            ->set('number', '11')->set('capacity', '6')->call('save')
            ->assertHasNoErrors()
            ->set('number', '11')->set('capacity', '2')->call('save')
            ->assertHasErrors(['number' => 'unique'])
            ->call('delete', $paid->id)
            ->assertSee('tiene órdenes o pagos en el historial');

        $new = DiningTable::where('number', 11)->sole();
        Livewire::test(Tables::class)->call('delete', $new->id)->assertSee('Mesa 11 eliminada.');

        expect(DiningTable::where('number', 11)->exists())->toBeFalse()
            ->and(AuditLog::where('action', 'table.created')->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'table.deleted')->exists())->toBeTrue();
    });

    it('no borra mesas con órdenes activas', function () {
        $table = makeTable(8);
        placeOrder(waiter(), $table);
        $this->actingAs(admin());

        Livewire::test(Tables::class)->call('delete', $table->id)->assertSee('La mesa 8 tiene órdenes activas.');
    });
});

describe('usuarios', function () {
    it('crea un usuario que puede iniciar sesión', function () {
        $this->actingAs(admin());

        Livewire::test(Users::class)
            ->set('name', 'Pedro Cruz')
            ->set('email', 'Pedro@Pinguin.test')
            ->set('role', 'mesero')
            ->set('password', 'pinguin2026')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'pedro@pinguin.test')->sole();
        expect(Hash::check('pinguin2026', $user->password))->toBeTrue()
            ->and(AuditLog::where('action', 'user.created')->sole()->data)->not->toHaveKey('password');
    });

    it('exige contraseña segura al crear y la deja opcional al editar', function () {
        $this->actingAs(admin());
        $user = waiter();

        Livewire::test(Users::class)
            ->set('name', 'X')->set('email', 'x@pinguin.test')->set('password', 'corta')
            ->call('save')->assertHasErrors('password');

        Livewire::test(Users::class)
            ->call('edit', $user->id)->set('name', 'Nombre nuevo')->call('save')->assertHasNoErrors();
        expect($user->refresh()->name)->toBe('Nombre nuevo');
    });

    it('al desactivar a un mesero revoca sus tokens de la app', function () {
        $waiter = waiter();
        $waiter->createToken('app');
        $this->actingAs(admin());

        Livewire::test(Users::class)->call('edit', $waiter->id)->set('active', false)->call('save');

        expect($waiter->refresh()->active)->toBeFalse()
            ->and($waiter->tokens()->count())->toBe(0);
    });

    it('el administrador no puede desactivarse ni quitarse el rol', function () {
        $admin = admin();
        $this->actingAs($admin);

        Livewire::test(Users::class)->call('edit', $admin->id)->set('active', false)->call('save')
            ->assertSee('No puedes desactivar tu propia cuenta.');
        Livewire::test(Users::class)->call('edit', $admin->id)->set('role', 'cocina')->call('save')
            ->assertSee('No puedes quitarte el rol de administrador.');

        expect($admin->refresh()->active)->toBeTrue();
    });
});

describe('órdenes', function () {
    it('filtra por fecha, estado, mesa y mesero', function () {
        $paid = paidOrder(1);
        $other = placeOrder($maria = waiter(['name' => 'María Olmedo']), makeTable(2));
        $this->travel(-3)->days();
        $old = placeOrder(waiter(), makeTable(3));
        $this->travelBack();
        $today = now()->setTimezone('America/Mexico_City')->format('Y-m-d');

        $this->actingAs(admin());
        $component = Livewire::test(OrdersIndex::class);
        $component->assertSee(route('admin.orders.show', $paid), false);

        $ids = fn () => $component->viewData('orders')->pluck('id')->all();

        $component->set('status', 'cobrada');
        expect($ids())->toBe([$paid->id]);

        $component->set('status', '')->set('waiter', (string) $maria->id);
        expect($ids())->toBe([$other->id]);

        $component->set('waiter', '')->set('table', (string) $old->table_id);
        expect($ids())->toBe([$old->id]);

        $component->call('clearFilters')->set('from', $today)->set('to', $today);
        expect($ids())->not->toContain($old->id)->toContain($paid->id, $other->id);
    });

    it('muestra el detalle con historial, duraciones y pagos', function () {
        $order = paidOrder(4, 8500, 'tarjeta', '10');
        $this->actingAs(admin());

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSee("Orden #{$order->id}")
            ->assertSeeInOrder(['Pendiente', 'En preparación', 'Lista', 'Entregada', 'Cobrada'])
            ->assertSee('$85.00')
            ->assertSee('Tarjeta')
            ->assertSee('Luis Hernández');
    });

    it('el administrador puede cancelar una orden pendiente con motivo', function () {
        $order = placeOrder(waiter(), makeTable());
        $this->actingAs($admin = admin());

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('cancel')
            ->assertHasErrors('reason')
            ->set('reason', 'Error de captura')
            ->call('cancel');

        expect($order->refresh()->status)->toBe(OrderStatus::Cancelada)
            ->and(AuditLog::where('action', 'order.cancelled')->sole()->user_id)->toBe($admin->id);
    });
});

it('lista pagos por fecha y método con totales', function () {
    paidOrder(1, 10000, 'efectivo', '5');
    paidOrder(2, 20000, 'tarjeta');
    $this->travel(-2)->days();
    paidOrder(3, 99900, 'efectivo');
    $this->travelBack();

    $this->actingAs(admin());
    $component = Livewire::test(Payments::class)
        ->assertSeeHtml('data-testid="total-all"')
        ->assertSee('$300.00')
        ->assertDontSee('$999.00');
    expect($component->viewData('payments')->total())->toBe(2);

    $component->set('method', 'tarjeta');
    expect($component->viewData('payments')->pluck('amount_cents')->all())->toBe([20000]);

    $component->call('clearFilters');
    expect($component->viewData('payments')->total())->toBe(Payment::count());
});
