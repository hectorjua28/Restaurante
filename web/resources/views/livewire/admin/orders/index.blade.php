@php
    use App\Support\LocalTime;
    use App\Support\Money;
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold">Órdenes</h1>
            <p class="text-sm text-pizarra/70">
                {{ $count }} {{ $count === 1 ? 'orden' : 'órdenes' }} · cobrado en el filtro: <span class="font-semibold">{{ Money::format($totalCents) }}</span>
            </p>
        </div>
    </div>

    <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div>
            <label for="from" class="label">Desde</label>
            <input id="from" type="date" wire:model.live="from" class="input">
        </div>
        <div>
            <label for="to" class="label">Hasta</label>
            <input id="to" type="date" wire:model.live="to" class="input">
        </div>
        <div>
            <label for="status" class="label">Estado</label>
            <select id="status" wire:model.live="status" class="input">
                <option value="">Todos</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="table" class="label">Mesa</label>
            <select id="table" wire:model.live="table" class="input">
                <option value="">Todas</option>
                @foreach ($tables as $t)
                    <option value="{{ $t->id }}">Mesa {{ $t->number }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="waiter" class="label">Mesero</label>
            <select id="waiter" wire:model.live="waiter" class="input">
                <option value="">Todos</option>
                @foreach ($waiters as $w)
                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="clearFilters" class="btn-ghost w-full">Limpiar filtros</button>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Mesa</th>
                    <th class="px-4 py-3">Mesero</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pizarra/10">
                @forelse ($orders as $order)
                    <tr wire:key="order-{{ $order->id }}" class="hover:bg-pizarra-50/50">
                        <td class="px-4 py-3 font-mono">{{ $order->id }}</td>
                        <td class="px-4 py-3">{{ LocalTime::format($order->created_at) }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $order->diningTable->number }}</td>
                        <td class="px-4 py-3">{{ $order->waiter->name }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$order->status" /></td>
                        <td class="px-4 py-3 text-right font-semibold">{{ Money::format($order->total_cents) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-semibold text-st-preparacion hover:underline">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-pizarra/60">No hay órdenes con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
