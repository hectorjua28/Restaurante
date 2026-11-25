<?php

namespace App\Livewire\Admin;

use App\Actions\Cash\CashSummary;
use App\Actions\Cash\CloseCash;
use App\Exceptions\BusinessRuleException;
use App\Models\CashClosing;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Corte de caja')]
class CashClosings extends Component
{
    use WithPagination;

    public string $counted = '';

    public string $notes = '';

    public string $flash = '';

    public string $flashType = 'success';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'counted' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ['counted.regex' => 'Escribe el efectivo contado en pesos, p. ej. 1520 o 1520.50.'];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['counted' => 'efectivo contado', 'notes' => 'notas'];
    }

    public function close(CloseCash $action): void
    {
        $this->validate();

        try {
            $closing = $action->handle(auth()->user(), Money::toCents($this->counted), $this->notes);
            $this->reset('counted', 'notes');
            $diff = $closing->difference_cents;
            $this->notify(match (true) {
                $diff === 0 => 'Corte realizado. La caja cuadra exactamente.',
                $diff > 0 => 'Corte realizado. Sobran '.Money::format($diff).'.',
                default => 'Corte realizado. Faltan '.Money::format(-$diff).'.',
            });
            $this->resetPage();
        } catch (BusinessRuleException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }

    public function render(CashSummary $summary): View
    {
        $current = $summary->handle();
        $countedCents = preg_match('/^\d{1,7}(\.\d{1,2})?$/', $this->counted) ? Money::toCents($this->counted) : null;

        return view('livewire.admin.cash-closings', [
            'current' => $current,
            'previewDiff' => $countedCents === null ? null : $countedCents - $current['expected_cash_cents'],
            'closings' => CashClosing::with('user')->latest('period_end')->latest('id')->paginate(10),
        ]);
    }
}
