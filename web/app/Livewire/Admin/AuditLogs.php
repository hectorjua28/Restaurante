<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bitácora de auditoría: solo lectura (la tabla tampoco permite editar ni borrar).
 */
#[Layout('components.layouts.app')]
#[Title('Bitácora')]
class AuditLogs extends Component
{
    use WithPagination;

    /** Etiquetas legibles para cada acción registrada. */
    public const ACTIONS = [
        'order.created' => 'Orden creada',
        'order.cancelled' => 'Orden cancelada',
        'table.charged' => 'Mesa cobrada',
        'cash.closed' => 'Corte de caja',
        'product.created' => 'Platillo creado',
        'product.updated' => 'Platillo editado',
        'product.deleted' => 'Platillo eliminado',
        'product.sold_out' => 'Platillo agotado',
        'product.available' => 'Platillo disponible',
        'category.created' => 'Categoría creada',
        'category.updated' => 'Categoría editada',
        'category.deleted' => 'Categoría eliminada',
        'table.created' => 'Mesa creada',
        'table.updated' => 'Mesa editada',
        'table.deleted' => 'Mesa eliminada',
        'user.created' => 'Usuario creado',
        'user.updated' => 'Usuario editado',
    ];

    #[Url(as: 'accion', except: '')]
    public string $action = '';

    #[Url(as: 'usuario', except: '')]
    public string $user = '';

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('action', 'user', 'from', 'to');
        $this->resetPage();
    }

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function subject(AuditLog $log): string
    {
        if ($log->auditable_type === null) {
            return '—';
        }
        $names = [
            'App\Models\Order' => 'Orden',
            'App\Models\Product' => 'Platillo',
            'App\Models\Category' => 'Categoría',
            'App\Models\DiningTable' => 'Mesa',
            'App\Models\User' => 'Usuario',
            'App\Models\CashClosing' => 'Corte',
        ];

        $name = $names[$log->auditable_type] ?? class_basename($log->auditable_type);

        // El id de la mesa no es su número visible; se aclara para no confundir.
        return $log->auditable_type === 'App\Models\DiningTable'
            ? "{$name} (registro {$log->auditable_id})"
            : "{$name} #{$log->auditable_id}";
    }

    public function render(): View
    {
        $zone = LocalTime::zone();

        return view('livewire.admin.audit-logs', [
            'logs' => AuditLog::query()
                ->with('user')
                ->when(array_key_exists($this->action, self::ACTIONS), fn ($q) => $q->where('action', $this->action))
                ->when(ctype_digit($this->user), fn ($q) => $q->where('user_id', (int) $this->user))
                ->when(OrdersIndex::validDate($this->from), fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($this->from, $zone)->startOfDay()->utc()))
                ->when(OrdersIndex::validDate($this->to), fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($this->to, $zone)->endOfDay()->utc()))
                ->latest()
                ->latest('id')
                ->paginate(25),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => self::ACTIONS,
        ]);
    }
}
