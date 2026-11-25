<?php

namespace App\Actions\Payments;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cobra todas las órdenes entregadas de una mesa. Permite dividir la cuenta en varios pagos;
 * la suma de los montos debe ser exactamente el total calculado por el servidor.
 */
class ChargeTable
{
    public function __construct(
        private BuildBill $buildBill,
        private TransitionOrder $transition,
    ) {}

    /**
     * @param  list<array{method: PaymentMethod, amount_cents: int, tip_cents: int}>  $payments
     * @return Collection<int, Payment>
     */
    public function handle(DiningTable $table, User $by, array $payments): Collection
    {
        return DB::transaction(function () use ($table, $by, $payments) {
            $table = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();
            $bill = $this->buildBill->handle($table, lock: true);

            if (! $bill['can_charge']) {
                throw new BusinessRuleException((string) $bill['blocking_reason'], 'cannot_charge');
            }

            $paid = array_sum(array_column($payments, 'amount_cents'));
            if ($paid !== $bill['total_cents']) {
                throw new BusinessRuleException(
                    'Los pagos suman '.Money::format($paid).' pero el total de la cuenta es '.Money::format($bill['total_cents']).'.',
                    'amount_mismatch',
                );
            }

            $orderIds = $bill['orders']->pluck('id');
            $created = collect($payments)->map(function (array $p) use ($table, $by, $orderIds) {
                $payment = Payment::create([
                    'table_id' => $table->id,
                    'user_id' => $by->id,
                    'method' => $p['method'],
                    'amount_cents' => $p['amount_cents'],
                    'tip_cents' => $p['tip_cents'],
                ]);
                $payment->orders()->attach($orderIds);

                return $payment;
            });

            foreach ($bill['orders'] as $order) {
                $this->transition->handle($order, OrderStatus::Cobrada, $by);
            }

            Audit::log('table.charged', $table, [
                'orders' => $orderIds->all(),
                'total_cents' => $bill['total_cents'],
                'tip_cents' => array_sum(array_column($payments, 'tip_cents')),
                'payments' => collect($payments)->map(fn ($p) => [
                    'method' => $p['method']->value,
                    'amount_cents' => $p['amount_cents'],
                    'tip_cents' => $p['tip_cents'],
                ])->all(),
            ], $by->id);

            return $created;
        });
    }
}
