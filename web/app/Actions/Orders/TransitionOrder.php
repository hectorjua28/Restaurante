<?php

namespace App\Actions\Orders;

use App\Actions\Tables\RefreshTableStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Único punto donde cambia el estado de una orden.
 * Valida el flujo (sin saltos ni retrocesos), bloquea la fila y registra el historial.
 */
class TransitionOrder
{
    public function __construct(private RefreshTableStatus $refreshTable) {}

    public function handle(Order $order, OrderStatus $to, User $by, ?string $cancelReason = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $cancelReason) {
            /** @var Order $locked */
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertCanTransition($locked, $to);

            $from = $locked->status;
            $locked->status = $to;
            if ($to === OrderStatus::Cancelada) {
                $locked->cancel_reason = $cancelReason;
            }
            $locked->save();

            $locked->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            $this->refreshTable->handle($locked->table_id);

            return $locked->refresh();
        });
    }

    public function assertCanTransition(Order $order, OrderStatus $to): void
    {
        if ($order->status === OrderStatus::Cobrada) {
            throw new BusinessRuleException('La orden ya fue cobrada y no se puede modificar.', 'order_paid');
        }

        if ($order->status === OrderStatus::Cancelada) {
            throw new BusinessRuleException('La orden está cancelada y no se puede modificar.', 'order_cancelled');
        }

        if ($to === OrderStatus::Cancelada && $order->status !== OrderStatus::Pendiente) {
            throw new BusinessRuleException('Solo se pueden cancelar órdenes pendientes. Esta orden ya está '.mb_strtolower($order->status->label()).'.', 'invalid_transition');
        }

        if (! $order->status->canTransitionTo($to)) {
            throw new BusinessRuleException(
                "No se puede pasar de «{$order->status->label()}» a «{$to->label()}».",
                'invalid_transition',
            );
        }
    }
}
