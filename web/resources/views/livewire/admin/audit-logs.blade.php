@php
    use App\Livewire\Admin\AuditLogs;
    use App\Support\LocalTime;
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="text-2xl font-bold">Bitácora de auditoría</h1>
    <p class="mb-4 text-sm text-pizarra/70">Registro de solo lectura: quién hizo qué, sobre qué registro y cuándo.</p>

    <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="action" class="label">Acción</label>
            <select id="action" wire:model.live="action" class="input">
                <option value="">Todas</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user" class="label">Usuario</label>
            <select id="user" wire:model.live="user" class="input">
                <option value="">Todos</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="label">Desde</label>
            <input id="from" type="date" wire:model.live="from" class="input">
        </div>
        <div>
            <label for="to" class="label">Hasta</label>
            <input id="to" type="date" wire:model.live="to" class="input">
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="clearFilters" class="btn-ghost w-full">Limpiar filtros</button>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Acción</th>
                    <th class="px-4 py-3">Registro</th>
                    <th class="px-4 py-3">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pizarra/10 align-top">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap px-4 py-3">{{ LocalTime::format($log->created_at, 'd/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 font-semibold">{{ AuditLogs::label($log->action) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($log->auditable_type === 'App\Models\Order')
                                <a href="{{ route('admin.orders.show', $log->auditable_id) }}" wire:navigate class="text-st-preparacion hover:underline">{{ AuditLogs::subject($log) }}</a>
                            @else
                                {{ AuditLogs::subject($log) }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! empty($log->data))
                                <details>
                                    <summary class="cursor-pointer font-semibold text-st-preparacion">Ver detalle</summary>
                                    <pre class="mt-2 max-w-xl overflow-x-auto rounded-lg bg-pizarra-50 p-3 text-xs">{{ json_encode($log->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @else
                                <span class="text-pizarra/50">—</span>
                            @endif
                            @if ($log->ip_address)
                                <p class="text-xs text-pizarra/50">IP {{ $log->ip_address }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-pizarra/60">No hay registros con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
