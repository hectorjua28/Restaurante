<?php

namespace App\Livewire\Admin\Orders;

use App\Actions\Orders\CancelOrder;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Detalle de una orden con su historial de estados (y la duración de cada etapa) y sus pagos.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public Order $order;

    #[Validate('required|string|min:3|max:255', as: 'motivo')]
    public string $reason = '';

    public string $error = '';

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function cancel(CancelOrder $cancel): void
    {
        $this->authorize('cancel', $this->order);
        $this->validate();

        try {
            $cancel->handle($this->order, auth()->user(), trim($this->reason));
            $this->reset('reason', 'error');
        } catch (BusinessRuleException $e) {
            $this->error = $e->getMessage();
        }

        $this->order->refresh();
    }

    public function render(): View
    {
        $this->order->load(['items', 'diningTable', 'waiter', 'statusHistory.user', 'payments.user']);
        $history = $this->order->statusHistory->values();

        // Duración de cada etapa: tiempo hasta el siguiente cambio de estado.
        $steps = $history->map(fn ($entry, $i) => [
            'entry' => $entry,
            'minutes' => isset($history[$i + 1]) ? (int) $entry->created_at->diffInMinutes($history[$i + 1]->created_at) : null,
        ]);

        $first = $history->first();
        $last = $history->last();

        return view('livewire.admin.orders.show', [
            'steps' => $steps,
            'totalMinutes' => $first && $last && $first->isNot($last) ? (int) $first->created_at->diffInMinutes($last->created_at) : null,
        ])->title("Orden #{$this->order->id}");
    }
}
