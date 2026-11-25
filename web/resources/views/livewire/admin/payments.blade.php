@php
    use App\Support\LocalTime;
    use App\Support\Money;
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="mb-4 text-2xl font-bold">Pagos</h1>

    <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="from" class="label">Desde</label>
            <input id="from" type="date" wire:model.live="from" class="input">
        </div>
        <div>
            <label for="to" class="label">Hasta</label>
            <input id="to" type="date" wire:model.live="to" class="input">
        </div>
        <div>
            <label for="method" class="label">Método</label>
            <select id="method" wire:model.live="method" class="input">
                <option value="">Todos</option>
                @foreach ($methods as $m)
                    <option value="{{ $m->value }}">{{ $m->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="clearFilters" class="btn-ghost w-full">Ver todo</button>
        </div>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($totals as $t)
            <div class="card p-4" data-testid="total-{{ $t['method']->value }}">
                <p class="text-sm font-semibold text-pizarra/70">{{ $t['method']->label() }}</p>
                <p class="text-2xl font-extrabold">{{ Money::format($t['amount']) }}</p>
                <p class="text-xs text-pizarra/60">{{ $t['count'] }} {{ $t['count'] === 1 ? 'pago' : 'pagos' }} · propinas {{ Money::format($t['tips']) }}</p>
            </div>
        @endforeach
        <div class="card bg-pizarra p-4 text-white" data-testid="total-all">
            <p class="text-sm font-semibold text-white/70">Total</p>
            <p class="text-2xl font-extrabold">{{ Money::format($grandAmount) }}</p>
            <p class="text-xs text-white/70">propinas {{ Money::format($grandTips) }}</p>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Mesa</th>
                    <th class="px-4 py-3">Órdenes</th>
                    <th class="px-4 py-3">Cobró</th>
                    <th class="px-4 py-3">Método</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3 text-right">Propina</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pizarra/10">
                @forelse ($payments as $payment)
                    <tr wire:key="payment-{{ $payment->id }}">
                        <td class="px-4 py-3">{{ LocalTime::format($payment->created_at) }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $payment->diningTable->number }}</td>
                        <td class="px-4 py-3">
                            @foreach ($payment->orders as $o)
                                <a href="{{ route('admin.orders.show', $o->id) }}" wire:navigate class="font-mono text-st-preparacion hover:underline">#{{ $o->id }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </td>
                        <td class="px-4 py-3">{{ $payment->user->name }}</td>
                        <td class="px-4 py-3">{{ $payment->method->label() }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ Money::format($payment->amount_cents) }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($payment->tip_cents) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-pizarra/60">No hay pagos con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</div>
