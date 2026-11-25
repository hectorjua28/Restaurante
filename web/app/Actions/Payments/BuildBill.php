<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Calcula la cuenta de una mesa a partir de sus órdenes activas (fuente de verdad: servidor).
 */
class BuildBill
{
    /**
     * @return array{table: DiningTable, orders: Collection<int, Order>, total_cents: int, pending_count: int, can_charge: bool, blocking_reason: string|null}
     */
    public function handle(DiningTable $table, bool $lock = false): array
    {
        $query = Order::where('table_id', $table->id)
            ->whereIn('status', OrderStatus::active())
            ->with(['items', 'waiter'])
            ->orderBy('created_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        $orders = $query->get();
        $pending = $orders->filter(fn (Order $o) => $o->status !== OrderStatus::Entregada)->count();

        $reason = match (true) {
            $orders->isEmpty() => 'La mesa no tiene órdenes por cobrar.',
            $pending > 0 => $pending === 1
                ? 'Hay 1 orden que todavía no se entrega.'
                : "Hay {$pending} órdenes que todavía no se entregan.",
            default => null,
        };

        return [
            'table' => $table,
            'orders' => $orders,
            'total_cents' => (int) $orders->sum('total_cents'),
            'pending_count' => $pending,
            'can_charge' => $reason === null,
            'blocking_reason' => $reason,
        ];
    }
}
