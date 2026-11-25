<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Órdenes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    #[Url(as: 'mesa', except: '')]
    public string $table = '';

    #[Url(as: 'mesero', except: '')]
    public string $waiter = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'status', 'table', 'waiter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('from', 'to', 'status', 'table', 'waiter');
        $this->resetPage();
    }

    /** @return Builder<Order> */
    public static function filteredQuery(string $from, string $to, string $status, string $table, string $waiter): Builder
    {
        $zone = LocalTime::zone();

        return Order::query()
            ->when(self::validDate($from), fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($from, $zone)->startOfDay()->utc()))
            ->when(self::validDate($to), fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($to, $zone)->endOfDay()->utc()))
            ->when(OrderStatus::tryFrom($status), fn ($q, $s) => $q->where('status', $s))
            ->when(ctype_digit($table), fn ($q) => $q->where('table_id', (int) $table))
            ->when(ctype_digit($waiter), fn ($q) => $q->where('user_id', (int) $waiter));
    }

    public static function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    public function render(): View
    {
        $query = self::filteredQuery($this->from, $this->to, $this->status, $this->table, $this->waiter);

        return view('livewire.admin.orders.index', [
            'orders' => (clone $query)->with(['diningTable', 'waiter'])->latest()->latest('id')->paginate(20),
            'totalCents' => (int) (clone $query)->where('status', OrderStatus::Cobrada)->sum('total_cents'),
            'count' => (clone $query)->count(),
            'statuses' => OrderStatus::cases(),
            'tables' => DiningTable::orderBy('number')->get(['id', 'number']),
            'waiters' => User::where('role', 'mesero')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
