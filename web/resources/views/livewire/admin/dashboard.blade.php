@php
    use App\Support\LocalTime;
    use App\Support\Money;
    $occupancy = $tablesTotal > 0 ? (int) round($tablesBusy * 100 / $tablesTotal) : 0;
@endphp

<div wire:poll.10s>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold">Resumen del día</h1>
            <p class="text-sm text-pizarra/70">{{ ucfirst($now->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }} · actualizado {{ $now->format('H:i') }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card border-t-4 border-acento p-5">
            <p class="text-sm font-semibold text-pizarra/70">Recaudado hoy</p>
            <p class="mt-1 text-3xl font-extrabold" data-testid="collected">{{ Money::format($collectedCents) }}</p>
            <p class="mt-1 text-sm text-pizarra/60">{{ $paymentsCount }} {{ $paymentsCount === 1 ? 'pago' : 'pagos' }} · propinas {{ Money::format($tipsCents) }}</p>
        </div>
        <div class="card border-t-4 border-st-preparacion p-5">
            <p class="text-sm font-semibold text-pizarra/70">Órdenes activas</p>
            <p class="mt-1 text-3xl font-extrabold" data-testid="active-orders">{{ $activeOrders }}</p>
            <p class="mt-1 text-sm text-pizarra/60">{{ $readyOrders }} {{ $readyOrders === 1 ? 'lista' : 'listas' }} para entregar</p>
        </div>
        <div class="card border-t-4 border-st-lista p-5">
            <p class="text-sm font-semibold text-pizarra/70">Órdenes cerradas hoy</p>
            <p class="mt-1 text-3xl font-extrabold" data-testid="closed-orders">{{ $closedToday }}</p>
            <p class="mt-1 text-sm text-pizarra/60">{{ $cancelledToday }} {{ $cancelledToday === 1 ? 'cancelada' : 'canceladas' }}</p>
        </div>
        <div class="card border-t-4 border-pizarra p-5">
            <p class="text-sm font-semibold text-pizarra/70">Ocupación de mesas</p>
            <p class="mt-1 text-3xl font-extrabold" data-testid="occupancy">{{ $tablesBusy }} / {{ $tablesTotal }}</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-pizarra-50" role="progressbar" aria-valuenow="{{ $occupancy }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-st-preparacion" style="width: {{ $occupancy }}%"></div>
            </div>
            <p class="mt-1 text-sm text-pizarra/60">
                @foreach ($tablesByStatus as $label => $count){{ $label }}: {{ $count }}@if (! $loop->last) · @endif @endforeach
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-pizarra/10 px-5 py-3">
                <h2 class="font-bold">Últimas órdenes</h2>
                <a href="{{ route('admin.orders.index') }}" wire:navigate class="text-sm font-semibold text-st-preparacion hover:underline">Ver todas</a>
            </header>
            <ul class="divide-y divide-pizarra/10">
                @forelse ($latestOrders as $order)
                    <li>
                        <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-pizarra-50/60">
                            <span class="w-14 font-mono text-sm text-pizarra/60">#{{ $order->id }}</span>
                            <span class="flex-1">
                                <span class="font-semibold">Mesa {{ $order->diningTable->number }}</span>
                                <span class="text-sm text-pizarra/60">· {{ $order->waiter->name }} · {{ LocalTime::format($order->created_at, 'd/m H:i') }}</span>
                            </span>
                            <x-status-badge :status="$order->status" />
                            <span class="w-24 text-right font-semibold">{{ Money::format($order->total_cents) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-pizarra/60">Aún no hay órdenes.</li>
                @endforelse
            </ul>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-pizarra/10 px-5 py-3">
                <h2 class="font-bold">Últimos pagos</h2>
                <a href="{{ route('admin.payments') }}" wire:navigate class="text-sm font-semibold text-st-preparacion hover:underline">Ver todos</a>
            </header>
            <ul class="divide-y divide-pizarra/10">
                @forelse ($latestPayments as $payment)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="flex-1">
                            <span class="font-semibold">Mesa {{ $payment->diningTable->number }}</span>
                            <span class="text-sm text-pizarra/60">· {{ $payment->user->name }} · {{ LocalTime::format($payment->created_at, 'd/m H:i') }}</span>
                        </span>
                        <span class="rounded-full bg-pizarra-50 px-2 py-0.5 text-sm font-semibold">{{ $payment->method->label() }}</span>
                        <span class="w-28 text-right">
                            <span class="font-semibold">{{ Money::format($payment->amount_cents) }}</span>
                            @if ($payment->tip_cents > 0)
                                <span class="block text-xs text-pizarra/60">+ {{ Money::format($payment->tip_cents) }} propina</span>
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-pizarra/60">Aún no hay pagos.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
