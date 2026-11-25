<?php

use App\Actions\Cash\CloseCash;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\CashClosings;
use App\Livewire\Admin\Reports;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\Order;
use App\Models\Payment;
use Livewire\Livewire;

/**
 * Cobra una mesa con varios pagos. $payments: [[método, monto, propina], ...] en pesos.
 */
function chargeWith(int $tableNumber, int $priceCents, array $payments, int $qty = 1, ?string $productName = null): Order
{
    $waiter = waiter();
    $table = makeTable($tableNumber);
    $product = makeProduct(['price_cents' => $priceCents] + ($productName ? ['name' => $productName] : []));
    $order = deliverOrder(placeOrder($waiter, $table, $product, $qty));
    actingAsApi($waiter);
    test()->postJson("/api/v1/tables/{$table->id}/payments", [
        'payments' => array_map(fn ($p) => ['method' => $p[0], 'amount' => $p[1], 'tip' => $p[2] ?? '0'], $payments),
    ])->assertCreated();

    return $order->refresh();
}

it('las nuevas secciones solo las ve el administrador', function (string $uri) {
    $this->actingAs(cook())->get($uri)->assertForbidden();
    $this->actingAs(admin())->get($uri)->assertOk();
})->with(['/admin/corte-caja', '/admin/reportes', '/admin/bitacora']);

describe('corte de caja', function () {
    it('calcula totales por método, propinas, efectivo esperado y diferencia', function () {
        chargeWith(1, 10000, [['efectivo', '60', '10'], ['tarjeta', '40', '5']]);
        chargeWith(2, 25000, [['transferencia', '250']]);
        chargeWith(3, 8000, [['efectivo', '80', '0']]);
        $admin = admin();

        $closing = app(CloseCash::class)->handle($admin, 15500, 'Turno de la tarde');

        expect($closing->cash_cents)->toBe(14000)
            ->and($closing->card_cents)->toBe(4000)
            ->and($closing->transfer_cents)->toBe(25000)
            ->and($closing->tips_cents)->toBe(1500)
            ->and($closing->expected_cash_cents)->toBe(15000) // 140 de ventas + 10 de propina en efectivo
            ->and($closing->counted_cash_cents)->toBe(15500)
            ->and($closing->difference_cents)->toBe(500)
            ->and($closing->notes)->toBe('Turno de la tarde')
            ->and(Payment::whereNull('cash_closing_id')->count())->toBe(0)
            ->and(AuditLog::where('action', 'cash.closed')->sole()->user_id)->toBe($admin->id);
    });

    it('el siguiente corte solo incluye los pagos posteriores y empieza donde terminó el anterior', function () {
        chargeWith(1, 10000, [['efectivo', '100']]);
        $first = app(CloseCash::class)->handle(admin(), 10000);

        $this->travel(2)->hours();
        chargeWith(2, 5000, [['efectivo', '50']]);
        $second = app(CloseCash::class)->handle(admin(), 4000);

        expect($second->cash_cents)->toBe(5000)
            ->and($second->difference_cents)->toBe(-1000)
            ->and($second->period_start->equalTo($first->period_end))->toBeTrue()
            ->and(Payment::where('cash_closing_id', $second->id)->count())->toBe(1);
    });

    it('no hace corte si no hay pagos nuevos', function () {
        app(CloseCash::class)->handle(admin(), 0);
    })->throws(BusinessRuleException::class, 'No hay pagos desde el último corte de caja.');

    it('desde la pantalla muestra el turno, la diferencia en vivo y guarda el corte', function () {
        chargeWith(1, 12000, [['efectivo', '120', '20']]);
        $this->actingAs(admin());

        Livewire::test(CashClosings::class)
            ->assertSeeHtml('data-testid="cash">$120.00')
            ->assertSeeHtml('data-testid="expected">$140.00')
            ->set('counted', '130')
            ->assertSeeHtml('data-testid="preview-diff">Faltan $10.00')
            ->set('counted', '140')
            ->assertSeeHtml('data-testid="preview-diff">Cuadra')
            ->set('counted', 'abc')
            ->call('close')
            ->assertHasErrors('counted')
            ->set('counted', '140.00')
            ->call('close')
            ->assertSee('Corte realizado. La caja cuadra exactamente.')
            ->assertSee('No hay pagos desde el último corte.');

        expect(CashClosing::sole()->difference_cents)->toBe(0);
    });
});

describe('reportes', function () {
    beforeEach(function () {
        // Dos días con ventas conocidas (las horas son de Ciudad de México).
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 20)->setTime(14, 0));
        chargeWith(1, 4500, [['efectivo', '90', '10']], 2, 'Bocoles');
        $this->travelTo(now('America/Mexico_City')->setDate(2026, 11, 21)->setTime(23, 30)); // 05:30 UTC del día 22
        chargeWith(2, 3000, [['tarjeta', '30']], 1, 'Café de olla');
        chargeWith(3, 4500, [['transferencia', '45']], 1, 'Bocoles 2');
        $this->travelBack();
    });

    it('calcula ventas por día en hora local, totales, métodos y más vendidos', function () {
        $this->actingAs(admin());

        $component = Livewire::test(Reports::class)
            ->set('from', '2026-11-20')
            ->set('to', '2026-11-22')
            ->assertSeeHtml('data-testid="sales">$165.00')
            ->assertSeeHtml('data-testid="orders">3');

        $byDay = $component->viewData('byDay')->pluck('sales_cents', 'date')->all();
        expect($byDay)->toBe(['2026-11-20' => 9000, '2026-11-21' => 7500, '2026-11-22' => 0]);

        $top = $component->viewData('topProducts');
        expect($top->first())->toMatchArray(['name' => 'Bocoles', 'quantity' => 2, 'sales_cents' => 9000]);

        $methods = $component->viewData('byMethod')->pluck('sales_cents', 'label')->all();
        expect($methods)->toBe(['Efectivo' => 9000, 'Tarjeta' => 3000, 'Transferencia' => 4500]);
    });

    it('calcula tiempos de preparación y totales con el historial de estados', function () {
        $this->actingAs(admin());
        $times = Livewire::test(Reports::class)->set('from', '2026-11-20')->set('to', '2026-11-22')->viewData('times');

        expect($times['orders'])->toBe(3)
            ->and($times['prep'])->not->toBeNull()
            ->and($times['total'])->toBeGreaterThanOrEqual($times['prep']);
    });

    it('valida el rango de fechas', function () {
        $this->actingAs(admin());
        Livewire::test(Reports::class)
            ->set('from', '2026-11-25')
            ->set('to', '2026-11-20')
            ->assertSee('La fecha inicial no puede ser posterior a la final.');
    });

    it('exporta cada reporte a CSV con montos en pesos', function (string $type, string $expectedHeader, string $expectedRow) {
        $this->actingAs(admin());
        $response = $this->get("/admin/reportes/{$type}.csv?desde=2026-11-20&hasta=2026-11-22");

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        expect($csv)->toStartWith("\xEF\xBB\xBF".$expectedHeader)->toContain($expectedRow);
    })->with([
        'ventas' => ['ventas', 'Fecha,Pagos,"Ventas (MXN)","Propinas (MXN)"', '2026-11-20,1,90.00,10.00'],
        'platillos' => ['platillos', 'Lugar,Platillo,"Cantidad vendida","Ventas (MXN)"', '1,Bocoles,2,90.00'],
        'métodos' => ['metodos', 'Método,Pagos,"Ventas (MXN)","Propinas (MXN)"', 'Transferencia,1,45.00,0.00'],
        'tiempos' => ['tiempos', 'Orden,Mesa,Fecha,"Espera (min)","Preparación (min)","Total (min)"', ',1,"2026-11-20 14:00",'],
    ]);

    it('el CSV valida fechas y tipo, y es solo para administradores', function () {
        $this->actingAs(admin())->get('/admin/reportes/ventas.csv?desde=x&hasta=2026-11-22')->assertSessionHasErrors('desde');
        $this->actingAs(admin())->get('/admin/reportes/otro.csv?desde=2026-11-20&hasta=2026-11-22')->assertNotFound();
        $this->actingAs(cook())->get('/admin/reportes/ventas.csv?desde=2026-11-20&hasta=2026-11-22')->assertForbidden();
    });
});

describe('bitácora', function () {
    it('muestra cancelaciones, cobros y ediciones con filtros', function () {
        $order = placeOrder($waiter = waiter(['name' => 'María Olmedo']), makeTable(4));
        actingAsApi($waiter);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Se fue el cliente'])->assertOk();
        chargeWith(5, 2000, [['efectivo', '20']]);

        $this->actingAs(admin());
        $actions = fn ($c) => $c->viewData('logs')->pluck('action')->unique()->sort()->values()->all();

        $component = Livewire::test(AuditLogs::class)->assertSee('Se fue el cliente');
        expect($actions($component))->toContain('order.cancelled', 'table.charged', 'order.created');

        $component->set('action', 'order.cancelled')->assertSee("Orden #{$order->id}");
        expect($actions($component))->toBe(['order.cancelled']);

        $component->set('action', '')->set('user', (string) $waiter->id);
        expect($component->viewData('logs')->pluck('user_id')->unique()->all())->toBe([$waiter->id]);
    });

    it('es de solo lectura: no hay acciones para editar ni borrar', function () {
        $this->actingAs(admin());
        Livewire::test(AuditLogs::class)->assertDontSee('Eliminar')->assertDontSee('Editar');

        expect(fn () => AuditLog::create(['action' => 'x'])->delete())->toThrow(LogicException::class);
    });
});
