<?php

namespace App\Livewire\Kitchen;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tablero de cocina: pendiente → en preparación → lista, en orden de llegada.
 * Se actualiza solo con polling (wire:poll) porque el hosting no permite WebSockets.
 */
#[Layout('components.layouts.app')]
#[Title('Cocina')]
class Board extends Component
{
    use AuthorizesRequests;

    /** Filtro de estación: '' (todas), 'cocina' o 'barra'. */
    #[Url(as: 'estacion', except: '')]
    public string $station = '';

    public string $flash = '';

    public string $flashType = 'success';

    public function mount(): void
    {
        if (Station::tryFrom($this->station) === null) {
            $this->station = '';
        }
    }

    public function setStation(string $station): void
    {
        $this->station = Station::tryFrom($station) !== null ? $station : '';
    }

    public function advance(int $orderId, TransitionOrder $transition): void
    {
        $order = Order::findOrFail($orderId);
        $this->authorize('advance', $order);

        $next = $order->status->next();

        try {
            if ($next !== OrderStatus::EnPreparacion && $next !== OrderStatus::Lista) {
                $transition->assertCanTransition($order, $next ?? OrderStatus::Cobrada);

                throw new BusinessRuleException('La orden ya está lista; ahora la entrega el mesero.');
            }

            $order = $transition->handle($order, $next, auth()->user())->load('diningTable');
            $this->notify("Orden #{$order->id} (mesa {$order->diningTable->number}): {$order->status->label()}.");
        } catch (BusinessRuleException $e) {
            // P. ej. otra persona de cocina ya la avanzó: avisamos y el tablero se refresca.
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $stationEnum = Station::tryFrom($this->station);

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->whereIn('status', OrderStatus::kitchen())
            ->when($stationEnum, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('station', $stationEnum)))
            ->with(['items', 'diningTable', 'waiter'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $columns = [];
        foreach (OrderStatus::kitchen() as $status) {
            $columns[$status->value] = [
                'status' => $status,
                'orders' => $orders->filter(fn (Order $o) => $o->status === $status)->values(),
            ];
        }

        return view('livewire.kitchen.board', [
            'columns' => $columns,
            'stationEnum' => $stationEnum,
            'now' => now(),
        ]);
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }
}
