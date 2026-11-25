<?php

namespace App\Actions\Tables;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;

/**
 * Recalcula el estado de la mesa a partir de sus órdenes activas:
 * - sin órdenes activas            → libre
 * - todas las activas entregadas   → por cobrar
 * - cualquier otro caso            → ocupada
 */
class RefreshTableStatus
{
    public function handle(int $tableId): TableStatus
    {
        $statuses = Order::where('table_id', $tableId)
            ->whereIn('status', OrderStatus::active())
            ->pluck('status');

        $status = match (true) {
            $statuses->isEmpty() => TableStatus::Libre,
            $statuses->every(fn (OrderStatus $s) => $s === OrderStatus::Entregada) => TableStatus::PorCobrar,
            default => TableStatus::Ocupada,
        };

        DiningTable::whereKey($tableId)->update(['status' => $status]);

        return $status;
    }
}
