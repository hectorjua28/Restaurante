<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Services\Reports\SalesReport;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reportes')]
class Reports extends Component
{
    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    public string $error = '';

    public function mount(): void
    {
        $today = LocalTime::now();
        if (! OrdersIndex::validDate($this->from)) {
            $this->from = $today->copy()->subDays(6)->format('Y-m-d');
        }
        if (! OrdersIndex::validDate($this->to)) {
            $this->to = $today->format('Y-m-d');
        }
    }

    public function preset(int $days): void
    {
        $today = LocalTime::now();
        $this->to = $today->format('Y-m-d');
        $this->from = $today->copy()->subDays(max(0, $days - 1))->format('Y-m-d');
    }

    public function render(): View
    {
        $this->error = '';
        $valid = OrdersIndex::validDate($this->from) && OrdersIndex::validDate($this->to);

        if ($valid && $this->from > $this->to) {
            $this->error = 'La fecha inicial no puede ser posterior a la final.';
            $valid = false;
        }
        if ($valid && (strtotime($this->to) - strtotime($this->from)) / 86400 > 366) {
            $this->error = 'El rango máximo es de un año.';
            $valid = false;
        }

        $report = $valid ? new SalesReport($this->from, $this->to) : null;
        $byDay = $report?->salesByDay() ?? collect();

        return view('livewire.admin.reports', [
            'report' => $report,
            'totals' => $report?->totals(),
            'byDay' => $byDay,
            'maxDay' => max(1, (int) $byDay->max('sales_cents')),
            'topProducts' => $report?->topProducts() ?? collect(),
            'byMethod' => $report?->byMethod() ?? collect(),
            'times' => $report?->averageTimes(),
            'query' => ['desde' => $this->from, 'hasta' => $this->to],
        ]);
    }
}
