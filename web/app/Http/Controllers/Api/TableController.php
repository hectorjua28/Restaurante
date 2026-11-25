<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\BuildBill;
use App\Actions\Payments\ChargeTable;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChargeTableRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TableResource;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    /**
     * Listar mesas.
     *
     * Incluye en `meta.counts` los contadores por estado para la pantalla de mesas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(TableStatus::class)]]);

        $all = $this->query()->get();
        $status = $request->string('status')->value();
        $tables = $status !== ''
            ? $all->filter(fn (DiningTable $t) => $t->status->value === $status)->values()
            : $all;

        return TableResource::collection($tables)->additional([
            'meta' => [
                'counts' => [
                    'total' => $all->count(),
                    'libre' => $all->where('status', TableStatus::Libre)->count(),
                    'ocupada' => $all->where('status', TableStatus::Ocupada)->count(),
                    'por_cobrar' => $all->where('status', TableStatus::PorCobrar)->count(),
                ],
            ],
        ]);
    }

    /** Detalle de una mesa. */
    public function show(DiningTable $table): TableResource
    {
        return new TableResource($this->query()->findOrFail($table->id));
    }

    /**
     * Cuenta de la mesa.
     *
     * Total calculado por el servidor con todas las órdenes activas de la mesa.
     */
    public function bill(DiningTable $table, BuildBill $buildBill): JsonResponse
    {
        $bill = $buildBill->handle($table);

        return response()->json(['data' => [
            'table' => ['id' => $table->id, 'number' => $table->number, 'status' => $table->status->value],
            'orders' => OrderResource::collection($bill['orders']->load('diningTable')),
            'total_cents' => $bill['total_cents'],
            'pending_count' => $bill['pending_count'],
            'can_charge' => $bill['can_charge'],
            'blocking_reason' => $bill['blocking_reason'] ?? '',
        ]]);
    }

    /**
     * Cobrar la mesa.
     *
     * Cobra todas las órdenes entregadas. Se puede dividir en varios pagos.
     */
    public function charge(ChargeTableRequest $request, DiningTable $table, ChargeTable $charge): JsonResponse
    {
        $payments = $charge->handle($table, $request->user(), $request->payments());

        return response()->json([
            'message' => "Mesa {$table->number} cobrada.",
            'data' => PaymentResource::collection($payments),
        ], 201);
    }

    /** @return Builder<DiningTable> */
    private function query(): Builder
    {
        return DiningTable::query()
            ->withCount('activeOrders')
            ->withSum('activeOrders', 'total_cents')
            ->orderBy('number');
    }
}
