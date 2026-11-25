<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('crea un usuario por rol', function () {
    foreach (Role::cases() as $role) {
        expect(User::where('role', $role)->exists())->toBeTrue();
    }
    expect(User::where('email', 'admin@pinguin.test')->first()->role)->toBe(Role::Admin);
});

it('crea 10 mesas y un menú típico', function () {
    expect(DiningTable::count())->toBe(10)
        ->and(Category::count())->toBeGreaterThanOrEqual(4)
        ->and(Product::where('name', 'like', 'Bocoles%')->exists())->toBeTrue()
        ->and(Product::where('name', 'Café de olla')->exists())->toBeTrue()
        ->and(Product::where('name', 'like', 'Enchiladas%')->exists())->toBeTrue()
        ->and(Product::where('name', 'like', 'Agua de%')->exists())->toBeTrue();
});

it('crea órdenes de días anteriores con historial y pagos consistentes', function () {
    $paid = Order::where('status', OrderStatus::Cobrada)->with('statusHistory', 'payments', 'items')->get();

    expect($paid->count())->toBeGreaterThan(50);

    foreach ($paid as $order) {
        expect($order->items->sum('subtotal_cents'))->toBe($order->total_cents)
            ->and($order->statusHistory->pluck('to_status')->all())->toBe([
                OrderStatus::Pendiente, OrderStatus::EnPreparacion, OrderStatus::Lista,
                OrderStatus::Entregada, OrderStatus::Cobrada,
            ])
            ->and($order->payments->sum('amount_cents'))->toBe($order->total_cents);
    }

    expect(CashClosing::count())->toBe(7)
        ->and(Payment::whereNull('cash_closing_id')->count())->toBe(0);
});

it('deja órdenes activas hoy con sus mesas ocupadas', function () {
    $active = Order::whereIn('status', OrderStatus::active())->with('diningTable')->get();

    expect($active)->not->toBeEmpty();
    foreach ($active as $order) {
        expect($order->diningTable->status->value)->toBeIn(['ocupada', 'por_cobrar']);
    }
});

it('se puede ejecutar dos veces sin duplicar datos', function () {
    $orders = Order::count();
    $this->seed(DatabaseSeeder::class);

    expect(Order::count())->toBe($orders)
        ->and(DiningTable::count())->toBe(10)
        ->and(User::count())->toBe(4);
});

it('la bitácora de auditoría es solo de inserción', function () {
    $log = AuditLog::create(['action' => 'prueba']);

    expect(fn () => $log->update(['action' => 'otra']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
