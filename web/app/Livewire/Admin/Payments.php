<?php

namespace App\Livewire\Admin;

use App\Enums\PaymentMethod;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\Payment;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pagos')]
class Payments extends Component
{
    use WithPagination;

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'metodo', except: '')]
    public string $method = '';

    public function mount(): void
    {
        // Por defecto, los pagos de hoy.
        if ($this->from === '' && $this->to === '') {
            $this->from = $this->to = LocalTime::now()->format('Y-m-d');
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->from = $this->to = $this->method = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $zone = LocalTime::zone();

        $query = Payment::query()
            ->when(OrdersIndex::validDate($this->from), fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($this->from, $zone)->startOfDay()->utc()))
            ->when(OrdersIndex::validDate($this->to), fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($this->to, $zone)->endOfDay()->utc()))
            ->when(PaymentMethod::tryFrom($this->method), fn ($q, $m) => $q->where('method', $m));

        $byMethod = (clone $query)
            ->selectRaw('method, COUNT(*) as n, SUM(amount_cents) as amount, SUM(tip_cents) as tips')
            ->groupBy('method')
            ->get()
            ->keyBy(fn (Payment $row): string => (string) $row->getRawOriginal('method'));

        $totals = collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
            'method' => $m,
            'count' => (int) ($byMethod[$m->value]->n ?? 0),
            'amount' => (int) ($byMethod[$m->value]->amount ?? 0),
            'tips' => (int) ($byMethod[$m->value]->tips ?? 0),
        ]);

        return view('livewire.admin.payments', [
            'payments' => (clone $query)->with(['diningTable', 'user', 'orders:id'])->latest()->latest('id')->paginate(25),
            'totals' => $totals,
            'grandAmount' => $totals->sum('amount'),
            'grandTips' => $totals->sum('tips'),
            'methods' => PaymentMethod::cases(),
        ]);
    }
}
