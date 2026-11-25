<?php

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Resumen del día (en hora de Ciudad de México): recaudado, órdenes, ocupación y últimos movimientos.
 */
#[Layout('components.layouts.app')]
#[Title('Resumen')]
class Dashboard extends Component
{
    private function transitionsToday(OrderStatus $status, mixed $from, mixed $to): int
    {
        return OrderStatusHistory::where('to_status', $status)
            ->whereBetween('created_at', [$from, $to])
            ->distinct()
            ->count('order_id');
    }

    public function render(): View
    {
        [$from, $to] = LocalTime::dayRangeUtc();

        $paymentsToday = Payment::whereBetween('created_at', [$from, $to]);
        $tables = DiningTable::query()->get(['id', 'status']);

        // Se cuenta con el historial de estados (fuente de verdad), no con updated_at.
        $closedToday = $this->transitionsToday(OrderStatus::Cobrada, $from, $to);
        $cancelledToday = $this->transitionsToday(OrderStatus::Cancelada, $from, $to);

        return view('livewire.admin.dashboard', [
            'collectedCents' => (int) (clone $paymentsToday)->sum('amount_cents'),
            'tipsCents' => (int) (clone $paymentsToday)->sum('tip_cents'),
            'paymentsCount' => (clone $paymentsToday)->count(),
            'activeOrders' => Order::whereIn('status', OrderStatus::active())->count(),
            'readyOrders' => Order::where('status', OrderStatus::Lista)->count(),
            'closedToday' => $closedToday,
            'cancelledToday' => $cancelledToday,
            'tablesTotal' => $tables->count(),
            'tablesBusy' => $tables->where('status', '!=', TableStatus::Libre)->count(),
            'tablesByStatus' => collect(TableStatus::cases())->mapWithKeys(fn (TableStatus $s) => [
                $s->label() => $tables->where('status', $s)->count(),
            ]),
            'latestOrders' => Order::with(['diningTable', 'waiter'])->latest()->latest('id')->limit(8)->get(),
            'latestPayments' => Payment::with(['diningTable', 'user'])->latest()->latest('id')->limit(8)->get(),
            'now' => LocalTime::now(),
        ]);
    }
}
