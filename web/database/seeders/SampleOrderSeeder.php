<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\CashClosing;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Órdenes de ejemplo de los últimos 7 días (cobradas, con historial y pagos)
 * y algunas órdenes activas de hoy, para que el tablero y los reportes muestren datos.
 */
class SampleOrderSeeder extends Seeder
{
    private const DAYS = 7;

    public function run(): void
    {
        if (Order::exists()) {
            return; // No duplicar si ya se sembró.
        }

        mt_srand(2026); // Datos reproducibles.

        $waiters = User::where('role', Role::Mesero)->get();
        $admin = User::where('role', Role::Admin)->firstOrFail();
        $kitchen = User::where('role', Role::Cocina)->firstOrFail();
        $tables = DiningTable::orderBy('number')->get();
        $products = Product::with('modifiers')->get();
        $zone = config('app.display_timezone');

        // Días anteriores: todo cobrado y con corte de caja al cierre.
        for ($d = self::DAYS; $d >= 1; $d--) {
            $day = CarbonImmutable::now($zone)->subDays($d)->startOfDay();
            $payments = collect();
            $count = mt_rand(14, 26);

            for ($i = 0; $i < $count; $i++) {
                $createdAt = $day->setTime(mt_rand(9, 20), mt_rand(0, 59))->utc();
                $cancel = mt_rand(1, 100) <= 6;
                $order = $this->makeOrder($tables->random(), $waiters->random(), $products, $createdAt);

                if ($cancel) {
                    $this->transition($order, OrderStatus::Cancelada, $order->waiter, $createdAt->addMinutes(mt_rand(1, 4)));
                    $order->update(['cancel_reason' => 'El cliente cambió de opinión']);

                    continue;
                }

                $t = $createdAt;
                $this->transition($order, OrderStatus::EnPreparacion, $kitchen, $t = $t->addMinutes(mt_rand(1, 6)));
                $this->transition($order, OrderStatus::Lista, $kitchen, $t = $t->addMinutes(mt_rand(7, 22)));
                $this->transition($order, OrderStatus::Entregada, $order->waiter, $t = $t->addMinutes(mt_rand(1, 4)));
                $paidAt = $t->addMinutes(mt_rand(15, 50));
                $payments->push($this->pay($order, $paidAt));
                $this->transition($order, OrderStatus::Cobrada, $order->waiter, $paidAt);
            }

            $this->closeCash($payments, $admin, $day->setTime(22, 30)->utc());
        }

        // Hoy: algunas órdenes activas en distintos estados.
        $now = CarbonImmutable::now();
        $active = [
            [1, OrderStatus::Pendiente, 3],
            [3, OrderStatus::EnPreparacion, 12],
            [4, OrderStatus::Lista, 25],
            [6, OrderStatus::Entregada, 40],
            [6, OrderStatus::Pendiente, 2],
        ];

        foreach ($active as [$number, $status, $minutesAgo]) {
            $table = $tables->firstWhere('number', $number);
            $createdAt = $now->subMinutes($minutesAgo);
            $order = $this->makeOrder($table, $waiters->first(), $products, $createdAt);
            $t = $createdAt;
            foreach ([OrderStatus::EnPreparacion, OrderStatus::Lista, OrderStatus::Entregada] as $step) {
                if ($order->status === $status) {
                    break;
                }
                $by = $step === OrderStatus::Entregada ? $order->waiter : $kitchen;
                $this->transition($order, $step, $by, $t = $t->addMinutes(max(1, intdiv($minutesAgo, 4))));
            }
            $table->update(['status' => TableStatus::Ocupada]);
        }
    }

    /** @param Collection<int, Product> $products */
    private function makeOrder(DiningTable $table, User $waiter, Collection $products, CarbonImmutable $createdAt): Order
    {
        $order = new Order([
            'table_id' => $table->id,
            'user_id' => $waiter->id,
            'status' => OrderStatus::Pendiente,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $order->setCreatedAt($createdAt);
        $order->setUpdatedAt($createdAt);
        $order->save();

        $total = 0;
        foreach ($products->random(mt_rand(1, 4)) as $product) {
            $qty = mt_rand(1, 3);
            $mods = $product->modifiers->isNotEmpty() && mt_rand(0, 1)
                ? $product->modifiers->random(1)->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price_delta_cents' => $m->price_delta_cents])->values()->all()
                : [];
            $unit = $product->price_cents + array_sum(array_column($mods, 'price_delta_cents'));
            $subtotal = $unit * $qty;
            $total += $subtotal;

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'station' => $product->station,
                'quantity' => $qty,
                'unit_price_cents' => $unit,
                'modifiers' => $mods,
                'note' => mt_rand(1, 6) === 1 ? 'Sin picante, por favor' : null,
                'subtotal_cents' => $subtotal,
            ]);
        }

        $order->update(['total_cents' => $total]);
        $order->statusHistory()->create([
            'from_status' => null,
            'to_status' => OrderStatus::Pendiente,
            'user_id' => $waiter->id,
            'created_at' => $createdAt,
        ]);

        return $order->setRelation('waiter', $waiter);
    }

    private function transition(Order $order, OrderStatus $to, User $by, CarbonImmutable $at): void
    {
        $order->statusHistory()->create([
            'from_status' => $order->status,
            'to_status' => $to,
            'user_id' => $by->id,
            'created_at' => $at,
        ]);
        $order->status = $to;
        $order->setUpdatedAt($at);
        $order->save();
    }

    private function pay(Order $order, CarbonImmutable $at): Payment
    {
        $method = [PaymentMethod::Efectivo, PaymentMethod::Efectivo, PaymentMethod::Tarjeta, PaymentMethod::Transferencia][mt_rand(0, 3)];
        $tip = mt_rand(0, 2) === 0 ? 0 : intdiv($order->total_cents * 10, 100 * 100) * 100;

        $payment = new Payment([
            'table_id' => $order->table_id,
            'user_id' => $order->user_id,
            'method' => $method,
            'amount_cents' => $order->total_cents,
            'tip_cents' => $tip,
        ]);
        $payment->setCreatedAt($at);
        $payment->setUpdatedAt($at);
        $payment->save();
        $payment->orders()->attach($order->id);

        return $payment;
    }

    /** @param Collection<int, Payment> $payments */
    private function closeCash(Collection $payments, User $admin, CarbonImmutable $at): void
    {
        $sum = fn (PaymentMethod $m) => $payments->where('method', $m)->sum(fn (Payment $p) => $p->amount_cents + $p->tip_cents);
        $cash = $sum(PaymentMethod::Efectivo);
        $counted = $cash + [0, 0, -2000, 1000][mt_rand(0, 3)];
        $previous = CashClosing::latest('period_end')->first();

        $closing = CashClosing::create([
            'user_id' => $admin->id,
            'period_start' => $previous?->period_end,
            'period_end' => $at,
            'cash_cents' => $cash,
            'card_cents' => $sum(PaymentMethod::Tarjeta),
            'transfer_cents' => $sum(PaymentMethod::Transferencia),
            'tips_cents' => $payments->sum('tip_cents'),
            'expected_cash_cents' => $cash,
            'counted_cash_cents' => max(0, $counted),
            'difference_cents' => max(0, $counted) - $cash,
        ]);

        Payment::whereIn('id', $payments->pluck('id'))->update(['cash_closing_id' => $closing->id]);
    }
}
