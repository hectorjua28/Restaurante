<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reportes para un rango de fechas locales (America/Mexico_City), ambos días incluidos.
 * Las agrupaciones por día se hacen en PHP para que funcionen igual en MySQL y SQLite.
 */
class SalesReport
{
    public readonly CarbonImmutable $fromUtc;

    public readonly CarbonImmutable $toUtc;

    public function __construct(public readonly string $from, public readonly string $to)
    {
        $zone = LocalTime::zone();
        $this->fromUtc = CarbonImmutable::parse($from, $zone)->startOfDay()->utc();
        $this->toUtc = CarbonImmutable::parse($to, $zone)->endOfDay()->utc();
    }

    /**
     * Ventas por día (montos de los pagos, sin propina).
     *
     * @return Collection<int, array{date: string, label: string, sales_cents: int, tips_cents: int, payments: int}>
     */
    public function salesByDay(): Collection
    {
        $zone = LocalTime::zone();
        $payments = $this->payments()->get(['amount_cents', 'tip_cents', 'created_at'])
            ->groupBy(fn (Payment $p) => $p->created_at->copy()->setTimezone($zone)->format('Y-m-d'));

        /** @var list<CarbonInterface> $days */
        $days = iterator_to_array(CarbonPeriod::create($this->from, $this->to), false);

        return collect($days)->map(function ($day) use ($payments) {
            $key = $day->format('Y-m-d');
            $rows = $payments->get($key, collect());

            return [
                'date' => $key,
                'label' => $day->locale('es')->isoFormat('ddd D MMM'),
                'sales_cents' => (int) $rows->sum('amount_cents'),
                'tips_cents' => (int) $rows->sum('tip_cents'),
                'payments' => $rows->count(),
            ];
        })->values();
    }

    /** @return array{sales_cents: int, tips_cents: int, orders: int, average_ticket_cents: int} */
    public function totals(): array
    {
        $sales = (int) $this->payments()->sum('amount_cents');
        $orders = $this->paidOrders()->count();

        return [
            'sales_cents' => $sales,
            'tips_cents' => (int) $this->payments()->sum('tip_cents'),
            'orders' => $orders,
            'average_ticket_cents' => $orders > 0 ? intdiv($sales, $orders) : 0,
        ];
    }

    /**
     * Platillos más vendidos (órdenes cobradas del rango).
     *
     * @return Collection<int, array{product_id: int, name: string, quantity: int, sales_cents: int}>
     */
    public function topProducts(int $limit = 10): Collection
    {
        return OrderItem::query()
            ->whereIn('order_id', $this->paidOrders()->select('id'))
            ->selectRaw('product_id, MAX(product_name) as name, SUM(quantity) as qty, SUM(subtotal_cents) as sales')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->orderByDesc('sales')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->getAttribute('name'),
                'quantity' => (int) $row->getAttribute('qty'),
                'sales_cents' => (int) $row->getAttribute('sales'),
            ])
            ->values();
    }

    /**
     * Ventas por método de pago.
     *
     * @return Collection<int, array{method: PaymentMethod, label: string, count: int, sales_cents: int, tips_cents: int}>
     */
    public function byMethod(): Collection
    {
        $rows = $this->payments()->get(['method', 'amount_cents', 'tip_cents']);

        return collect(PaymentMethod::cases())->map(function (PaymentMethod $m) use ($rows) {
            $of = $rows->filter(fn (Payment $p) => $p->method === $m);

            return [
                'method' => $m,
                'label' => $m->label(),
                'count' => $of->count(),
                'sales_cents' => (int) $of->sum('amount_cents'),
                'tips_cents' => (int) $of->sum('tip_cents'),
            ];
        })->values();
    }

    /**
     * Tiempos por orden calculados con order_status_history (en minutos).
     * - espera: pendiente → en preparación
     * - preparación: en preparación → lista
     * - total: pendiente → cobrada
     *
     * @return Collection<int, array{order_id: int, table: int, created_at: CarbonImmutable, wait: ?float, prep: ?float, total: ?float}>
     */
    public function orderTimes(): Collection
    {
        $orders = $this->paidOrders()->with('diningTable:id,number')->get(['id', 'table_id', 'created_at']);
        $history = OrderStatusHistory::whereIn('order_id', $orders->pluck('id'))
            ->get(['order_id', 'to_status', 'created_at'])
            ->groupBy('order_id');

        return $orders->map(function (Order $order) use ($history) {
            $at = $history->get($order->id, collect())
                ->mapWithKeys(fn (OrderStatusHistory $h) => [$h->to_status->value => CarbonImmutable::instance($h->created_at)]);

            $minutes = fn (string $a, string $b) => isset($at[$a], $at[$b])
                ? round($at[$a]->diffInSeconds($at[$b]) / 60, 1)
                : null;

            return [
                'order_id' => $order->id,
                'table' => $order->diningTable->number,
                'created_at' => CarbonImmutable::instance($order->created_at),
                'wait' => $minutes(OrderStatus::Pendiente->value, OrderStatus::EnPreparacion->value),
                'prep' => $minutes(OrderStatus::EnPreparacion->value, OrderStatus::Lista->value),
                'total' => $minutes(OrderStatus::Pendiente->value, OrderStatus::Cobrada->value),
            ];
        })->sortBy('order_id')->values();
    }

    /** @return array{wait: ?float, prep: ?float, total: ?float, orders: int} */
    public function averageTimes(): array
    {
        $times = $this->orderTimes();
        $avg = fn (string $key) => ($values = $times->pluck($key)->filter(fn ($v) => $v !== null))->isEmpty()
            ? null
            : round((float) $values->avg(), 1);

        return ['wait' => $avg('wait'), 'prep' => $avg('prep'), 'total' => $avg('total'), 'orders' => $times->count()];
    }

    /** @return Builder<Payment> */
    private function payments()
    {
        return Payment::whereBetween('created_at', [$this->fromUtc, $this->toUtc]);
    }

    /**
     * Órdenes cobradas cuyo cobro ocurrió en el rango.
     *
     * @return Builder<Order>
     */
    private function paidOrders()
    {
        return Order::where('status', OrderStatus::Cobrada)
            ->whereHas('statusHistory', fn ($q) => $q
                ->where('to_status', OrderStatus::Cobrada)
                ->whereBetween('created_at', [$this->fromUtc, $this->toUtc]));
    }
}
