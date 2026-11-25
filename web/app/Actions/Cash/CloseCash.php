<?php

namespace App\Actions\Cash;

use App\Exceptions\BusinessRuleException;
use App\Models\CashClosing;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Corte de caja: cierra todos los pagos desde el corte anterior, guarda los totales por método,
 * el efectivo contado y la diferencia (contado − esperado).
 */
class CloseCash
{
    public function __construct(private CashSummary $summary) {}

    public function handle(User $by, int $countedCashCents, ?string $notes = null): CashClosing
    {
        if ($countedCashCents < 0) {
            throw new BusinessRuleException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($by, $countedCashCents, $notes) {
            // Bloquea los pagos pendientes para que un cobro simultáneo no quede a medias entre dos cortes.
            $payments = $this->summary->pending(lock: true)->get(['id', 'method', 'amount_cents', 'tip_cents', 'created_at']);

            if ($payments->isEmpty()) {
                throw new BusinessRuleException('No hay pagos desde el último corte de caja.', 'nothing_to_close');
            }

            $totals = CashSummary::summarize($payments, CashClosing::max('period_end'));

            $closing = CashClosing::create([
                'user_id' => $by->id,
                'period_start' => $totals['period_start'],
                'period_end' => now(),
                'cash_cents' => $totals['cash_cents'],
                'card_cents' => $totals['card_cents'],
                'transfer_cents' => $totals['transfer_cents'],
                'tips_cents' => $totals['tips_cents'],
                'expected_cash_cents' => $totals['expected_cash_cents'],
                'counted_cash_cents' => $countedCashCents,
                'difference_cents' => $countedCashCents - $totals['expected_cash_cents'],
                'notes' => ($notes = trim((string) $notes)) === '' ? null : $notes,
            ]);

            $this->summary->pending()->whereIn('id', $payments->pluck('id'))->update(['cash_closing_id' => $closing->id]);

            Audit::log('cash.closed', $closing, [
                'pagos' => $payments->count(),
                'esperado_cents' => $closing->expected_cash_cents,
                'contado_cents' => $closing->counted_cash_cents,
                'diferencia_cents' => $closing->difference_cents,
            ], $by->id);

            return $closing;
        });
    }
}
