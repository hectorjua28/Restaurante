<?php

namespace App\Actions\Cash;

use App\Enums\PaymentMethod;
use App\Models\CashClosing;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Totales del turno actual: todos los pagos que todavía no pertenecen a un corte.
 *
 * - Ventas por método = suma de los montos (sin propina).
 * - Efectivo esperado en caja = ventas en efectivo + propinas pagadas en efectivo.
 */
class CashSummary
{
    /**
     * @return array{
     *     period_start: Carbon|null, payments_count: int,
     *     cash_cents: int, card_cents: int, transfer_cents: int, tips_cents: int,
     *     cash_tips_cents: int, expected_cash_cents: int, sales_cents: int
     * }
     */
    public function handle(bool $lock = false): array
    {
        $payments = $this->pending($lock)->get(['id', 'method', 'amount_cents', 'tip_cents', 'created_at']);

        return self::summarize($payments, CashClosing::max('period_end'));
    }

    /** @return Builder<Payment> */
    public function pending(bool $lock = false): Builder
    {
        $query = Payment::whereNull('cash_closing_id');

        return $lock ? $query->lockForUpdate() : $query;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array{
     *     period_start: Carbon|null, payments_count: int,
     *     cash_cents: int, card_cents: int, transfer_cents: int, tips_cents: int,
     *     cash_tips_cents: int, expected_cash_cents: int, sales_cents: int
     * }
     */
    public static function summarize(Collection $payments, mixed $lastClosingEnd = null): array
    {
        $by = fn (PaymentMethod $m) => $payments->filter(fn (Payment $p) => $p->method === $m);

        $cash = (int) $by(PaymentMethod::Efectivo)->sum('amount_cents');
        $cashTips = (int) $by(PaymentMethod::Efectivo)->sum('tip_cents');
        $card = (int) $by(PaymentMethod::Tarjeta)->sum('amount_cents');
        $transfer = (int) $by(PaymentMethod::Transferencia)->sum('amount_cents');

        $start = $lastClosingEnd !== null
            ? Carbon::parse($lastClosingEnd)
            : $payments->min('created_at');

        return [
            'period_start' => $start ? Carbon::parse($start) : null,
            'payments_count' => $payments->count(),
            'cash_cents' => $cash,
            'card_cents' => $card,
            'transfer_cents' => $transfer,
            'tips_cents' => (int) $payments->sum('tip_cents'),
            'cash_tips_cents' => $cashTips,
            'expected_cash_cents' => $cash + $cashTips,
            'sales_cents' => $cash + $card + $transfer,
        ];
    }
}
