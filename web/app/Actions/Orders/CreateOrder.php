<?php

namespace App\Actions\Orders;

use App\Actions\Tables\RefreshTableStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Crea una comanda. Los precios se toman SIEMPRE del servidor y se congelan en cada renglón.
 *
 * @phpstan-type ItemInput array{product_id:int, quantity:int, modifier_ids?:list<int>, note?:string|null}
 */
class CreateOrder
{
    public function __construct(private RefreshTableStatus $refreshTable) {}

    /**
     * @param  list<ItemInput>  $items
     * @return array{0: Order, 1: bool} La orden y si se creó (false = ya existía por idempotencia).
     */
    public function handle(User $waiter, int $tableId, array $items, string $idempotencyKey): array
    {
        if ($existing = $this->findExisting($idempotencyKey, $waiter)) {
            return [$existing, false];
        }

        try {
            $order = DB::transaction(function () use ($waiter, $tableId, $items, $idempotencyKey) {
                // Bloquea la mesa para que dos meseros no choquen al enviar o cobrar al mismo tiempo.
                DiningTable::whereKey($tableId)->lockForUpdate()->firstOrFail();

                // Revisión dentro de la transacción por si otro request ganó la carrera.
                if ($existing = $this->findExisting($idempotencyKey, $waiter)) {
                    return $existing;
                }

                $rows = $this->buildRows($items);

                $order = Order::create([
                    'table_id' => $tableId,
                    'user_id' => $waiter->id,
                    'status' => OrderStatus::Pendiente,
                    'total_cents' => array_sum(array_column($rows, 'subtotal_cents')),
                    'idempotency_key' => $idempotencyKey,
                ]);
                $order->items()->createMany($rows);
                $order->statusHistory()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::Pendiente,
                    'user_id' => $waiter->id,
                    'created_at' => now(),
                ]);

                $this->refreshTable->handle($tableId);
                Audit::log('order.created', $order, ['total_cents' => $order->total_cents, 'items' => count($rows)], $waiter->id);

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // Dos envíos simultáneos con la misma clave: devolvemos la que quedó guardada.
            return [$this->findExisting($idempotencyKey, $waiter) ?? throw new BusinessRuleException('No se pudo registrar la orden.'), false];
        }

        return [$order, $order->wasRecentlyCreated];
    }

    private function findExisting(string $key, User $waiter): ?Order
    {
        $order = Order::where('idempotency_key', $key)->first();

        if ($order && $order->user_id !== $waiter->id) {
            throw new BusinessRuleException('La clave de idempotencia ya fue usada por otra orden.', 'idempotency_conflict');
        }

        return $order;
    }

    /**
     * @param  list<ItemInput>  $items
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $items): array
    {
        $products = Product::with('modifiers')
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($items as $item) {
            /** @var Product|null $product */
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new BusinessRuleException('Uno de los platillos ya no existe en el menú.', 'product_missing');
            }
            if (! $product->available) {
                throw new BusinessRuleException("«{$product->name}» está agotado.", 'product_sold_out');
            }

            $modifierIds = array_values(array_unique($item['modifier_ids'] ?? []));
            $modifiers = $product->modifiers->whereIn('id', $modifierIds);
            if ($modifiers->count() !== count($modifierIds)) {
                throw new BusinessRuleException("Un modificador no corresponde a «{$product->name}».", 'invalid_modifier');
            }

            $unit = $product->price_cents + (int) $modifiers->sum('price_delta_cents');
            $quantity = (int) $item['quantity'];
            $note = isset($item['note']) ? trim((string) $item['note']) : '';

            $rows[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'station' => $product->station,
                'quantity' => $quantity,
                'unit_price_cents' => $unit,
                'modifiers' => $modifiers->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price_delta_cents' => $m->price_delta_cents,
                ])->values()->all(),
                'note' => $note === '' ? null : $note,
                'subtotal_cents' => $unit * $quantity,
            ];
        }

        return $rows;
    }
}
