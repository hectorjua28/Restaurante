<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\TransitionOrder;
use App\Actions\Products\SetProductAvailability;
use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    /**
     * Órdenes para cocina.
     *
     * Pendientes, en preparación y listas, en orden de llegada. Filtro opcional por estación.
     */
    public function orders(Request $request): AnonymousResourceCollection
    {
        $request->validate(['station' => ['nullable', Rule::enum(Station::class)]]);
        $station = $request->string('station')->value();

        $orders = Order::query()
            ->whereIn('status', OrderStatus::kitchen())
            ->when($station !== '', fn ($q) => $q->whereHas('items', fn ($i) => $i->where('station', $station)))
            ->with(['items', 'diningTable', 'waiter'])
            ->orderBy('created_at')
            ->get();

        return OrderResource::collection($orders);
    }

    /**
     * Avanzar orden.
     *
     * pendiente → en preparación → lista. La entrega la hace el mesero.
     */
    public function advance(Request $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $this->authorize('advance', $order);

        $next = $order->status->next();
        if ($next !== OrderStatus::EnPreparacion && $next !== OrderStatus::Lista) {
            // Mensaje específico si está cobrada/cancelada; si no, recordamos que entrega el mesero.
            $transition->assertCanTransition($order, $next ?? OrderStatus::Cobrada);

            throw new BusinessRuleException('La orden ya está lista; ahora la entrega el mesero.', 'invalid_transition');
        }

        $order = $transition->handle($order, $next, $request->user());

        return new OrderResource($order->load(['items', 'diningTable', 'waiter']));
    }

    /** Marcar platillo como agotado o disponible. */
    public function availability(Request $request, Product $product, SetProductAvailability $action): ProductResource
    {
        $this->authorize('toggleAvailability', $product);
        $request->validate(['available' => ['required', 'boolean']]);

        return new ProductResource($action->handle($product, $request->boolean('available'), $request->user())->load('modifiers'));
    }
}
