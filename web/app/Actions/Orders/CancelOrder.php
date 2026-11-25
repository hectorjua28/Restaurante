<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(private TransitionOrder $transition) {}

    public function handle(Order $order, User $by, string $reason): Order
    {
        return DB::transaction(function () use ($order, $by, $reason) {
            $order = $this->transition->handle($order, OrderStatus::Cancelada, $by, $reason);
            Audit::log('order.cancelled', $order, ['reason' => $reason, 'total_cents' => $order->total_cents], $by->id);

            return $order;
        });
    }
}
