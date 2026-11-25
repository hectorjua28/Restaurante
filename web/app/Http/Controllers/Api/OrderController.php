<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelOrderRequest;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const RELATIONS = ['items', 'diningTable', 'waiter'];

    /**
     * Mis órdenes.
     *
     * Por defecto devuelve las activas. Filtra con `status=lista,entregada`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $statuses = array_values(array_filter(explode(',', $request->string('status')->value())));
        validator(['status' => $statuses], ['status.*' => [Rule::enum(OrderStatus::class)]])->validate();

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', $statuses ?: OrderStatus::active())
            ->with(self::RELATIONS)
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return OrderResource::collection($orders);
    }

    /**
     * Enviar comanda a cocina.
     *
     * Idempotente: si la `idempotency_key` ya existe, devuelve la orden original con 200.
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        [$order, $created] = $createOrder->handle(
            $request->user(),
            $request->integer('table_id'),
            $request->items(),
            $request->string('idempotency_key')->value(),
        );

        return (new OrderResource($order->load(self::RELATIONS)))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /** Detalle de una orden con su historial de estados. */
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load([...self::RELATIONS, 'statusHistory.user']));
    }

    /** Marcar como entregada (solo desde «lista»). */
    public function deliver(Request $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $this->authorize('deliver', $order);

        $order = $transition->handle($order, OrderStatus::Entregada, $request->user());

        return new OrderResource($order->load(self::RELATIONS));
    }

    /** Cancelar (solo pendientes, con motivo). */
    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancel): OrderResource
    {
        $order = $cancel->handle($order, $request->user(), $request->string('reason')->trim()->value());

        return new OrderResource($order->load(self::RELATIONS));
    }
}
