<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;

/** Mesa con dos órdenes entregadas: 2 × $45 + 1 × $30 = $120. */
function tableReadyToCharge(): array
{
    $waiter = waiter();
    $table = makeTable(7);
    $a = deliverOrder(placeOrder($waiter, $table, makeProduct(['price_cents' => 4500]), 2));
    $b = deliverOrder(placeOrder($waiter, $table, makeProduct(['price_cents' => 3000]), 1));
    actingAsApi($waiter);

    return [$waiter, $table, $a, $b];
}

describe('cuenta', function () {
    it('calcula el total en el servidor', function () {
        [, $table] = tableReadyToCharge();

        $this->getJson("/api/v1/tables/{$table->id}/bill")
            ->assertOk()
            ->assertJsonPath('data.table.number', 7)
            ->assertJsonPath('data.total_cents', 12000)
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.can_charge', true)
            ->assertJsonPath('data.blocking_reason', '');
    });

    it('indica que no se puede cobrar si hay órdenes sin entregar', function () {
        $waiter = waiter();
        $table = makeTable();
        placeOrder($waiter, $table);
        actingAsApi($waiter);

        $this->getJson("/api/v1/tables/{$table->id}/bill")
            ->assertJsonPath('data.can_charge', false)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.blocking_reason', 'Hay 1 orden que todavía no se entrega.');
    });

    it('una mesa libre no tiene nada que cobrar', function () {
        actingAsApi(waiter());
        $table = makeTable();

        $this->getJson("/api/v1/tables/{$table->id}/bill")
            ->assertJsonPath('data.total_cents', 0)
            ->assertJsonPath('data.can_charge', false)
            ->assertJsonPath('data.blocking_reason', 'La mesa no tiene órdenes por cobrar.');
    });
});

describe('cobro', function () {
    it('cobra la mesa con un solo pago y propina', function () {
        [$waiter, $table, $a, $b] = tableReadyToCharge();

        $this->postJson("/api/v1/tables/{$table->id}/payments", [
            'payments' => [['method' => 'efectivo', 'amount' => '120.00', 'tip' => '15.50']],
        ])->assertCreated()
            ->assertJsonPath('message', 'Mesa 7 cobrada.')
            ->assertJsonPath('data.0.method', 'efectivo')
            ->assertJsonPath('data.0.amount_cents', 12000)
            ->assertJsonPath('data.0.tip_cents', 1550);

        expect($a->refresh()->status)->toBe(OrderStatus::Cobrada)
            ->and($b->refresh()->status)->toBe(OrderStatus::Cobrada)
            ->and($table->refresh()->status->value)->toBe('libre')
            ->and(Payment::sole()->orders()->pluck('orders.id')->sort()->values()->all())->toBe([$a->id, $b->id])
            ->and($a->statusHistory()->get()->last()->to_status)->toBe(OrderStatus::Cobrada);

        $log = AuditLog::where('action', 'table.charged')->sole();
        expect($log->user_id)->toBe($waiter->id)
            ->and($log->data['total_cents'])->toBe(12000)
            ->and($log->data['tip_cents'])->toBe(1550);
    });

    it('permite dividir la cuenta entre varios métodos', function () {
        [, $table] = tableReadyToCharge();

        $this->postJson("/api/v1/tables/{$table->id}/payments", [
            'payments' => [
                ['method' => 'efectivo', 'amount' => '50'],
                ['method' => 'tarjeta', 'amount' => '40.50', 'tip' => '10'],
                ['method' => 'transferencia', 'amount' => '29.50'],
            ],
        ])->assertCreated()->assertJsonCount(3, 'data');

        expect((int) Payment::sum('amount_cents'))->toBe(12000)
            ->and((int) Payment::sum('tip_cents'))->toBe(1000);
    });

    it('rechaza pagos que no suman el total del servidor', function (string $amount) {
        [, $table, $a] = tableReadyToCharge();

        $this->postJson("/api/v1/tables/{$table->id}/payments", [
            'payments' => [['method' => 'efectivo', 'amount' => $amount]],
        ])->assertUnprocessable()->assertJsonPath('code', 'amount_mismatch');

        expect(Payment::count())->toBe(0)
            ->and($a->refresh()->status)->toBe(OrderStatus::Entregada)
            ->and($table->refresh()->status->value)->toBe('por_cobrar');
    })->with(['119.99', '120.01', '0']);

    it('no cobra si hay órdenes sin entregar', function () {
        $waiter = waiter();
        $table = makeTable();
        deliverOrder(placeOrder($waiter, $table, makeProduct(['price_cents' => 1000])));
        placeOrder($waiter, $table, makeProduct(['price_cents' => 1000]));
        actingAsApi($waiter);

        $this->postJson("/api/v1/tables/{$table->id}/payments", [
            'payments' => [['method' => 'efectivo', 'amount' => '20']],
        ])->assertUnprocessable()->assertJsonPath('code', 'cannot_charge');

        expect(Payment::count())->toBe(0);
    });

    it('no cobra dos veces la misma mesa', function () {
        [, $table] = tableReadyToCharge();
        $payload = ['payments' => [['method' => 'tarjeta', 'amount' => '120']]];

        $this->postJson("/api/v1/tables/{$table->id}/payments", $payload)->assertCreated();
        $this->postJson("/api/v1/tables/{$table->id}/payments", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La mesa no tiene órdenes por cobrar.');

        expect(Payment::count())->toBe(1);
    });

    it('una orden cobrada no se puede modificar', function () {
        [$waiter, $table, $a] = tableReadyToCharge();
        $this->postJson("/api/v1/tables/{$table->id}/payments", ['payments' => [['method' => 'efectivo', 'amount' => '120']]]);

        $this->postJson("/api/v1/orders/{$a->id}/cancel", ['reason' => 'Intento'])
            ->assertUnprocessable()->assertJsonPath('code', 'order_paid');
        $this->postJson("/api/v1/orders/{$a->id}/deliver")
            ->assertUnprocessable()->assertJsonPath('message', 'La orden ya fue cobrada y no se puede modificar.');

        actingAsApi(cook());
        $this->postJson("/api/v1/kitchen/orders/{$a->id}/advance")->assertUnprocessable()->assertJsonPath('code', 'order_paid');
    });

    it('valida métodos y montos', function (array $payment, string $field) {
        [, $table] = tableReadyToCharge();

        $this->postJson("/api/v1/tables/{$table->id}/payments", ['payments' => [$payment]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'método inválido' => [['method' => 'bitcoin', 'amount' => '120'], 'payments.0.method'],
        'monto con 3 decimales' => [['method' => 'efectivo', 'amount' => '120.001'], 'payments.0.amount'],
        'monto negativo' => [['method' => 'efectivo', 'amount' => '-120'], 'payments.0.amount'],
        'propina con texto' => [['method' => 'efectivo', 'amount' => '120', 'tip' => 'diez'], 'payments.0.tip'],
    ]);

    it('cualquier mesero puede cobrar una mesa compartida', function () {
        [, $table] = tableReadyToCharge();
        actingAsApi(waiter());

        $this->postJson("/api/v1/tables/{$table->id}/payments", ['payments' => [['method' => 'efectivo', 'amount' => '120']]])
            ->assertCreated();
    });

    it('las órdenes canceladas no se cobran', function () {
        $waiter = waiter();
        $table = makeTable();
        deliverOrder(placeOrder($waiter, $table, makeProduct(['price_cents' => 1000])));
        $cancelled = placeOrder($waiter, $table, makeProduct(['price_cents' => 5000]));
        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$cancelled->id}/cancel", ['reason' => 'Error'])->assertOk();

        $this->getJson("/api/v1/tables/{$table->id}/bill")->assertJsonPath('data.total_cents', 1000);
        $this->postJson("/api/v1/tables/{$table->id}/payments", ['payments' => [['method' => 'efectivo', 'amount' => '10']]])
            ->assertCreated();

        expect(Order::find($cancelled->id)->status)->toBe(OrderStatus::Cancelada);
    });
});
