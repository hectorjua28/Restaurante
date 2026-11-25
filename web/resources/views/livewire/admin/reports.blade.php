@php
    use App\Support\Money;
    $fmtMin = fn (?float $m) => $m === null ? '—' : ($m < 60 ? number_format($m, 1).' min' : intdiv((int) round($m), 60).' h '.str_pad((string) ((int) round($m) % 60), 2, '0', STR_PAD_LEFT).' min');
    $methodTotal = max(1, $byMethod->sum('sales_cents'));
    $csv = fn (string $type) => route('admin.reports.csv', ['tipo' => $type] + $query);
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="mb-4 text-2xl font-bold">Reportes</h1>

    <div class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label for="from" class="label">Desde</label>
            <input id="from" type="date" wire:model.live="from" class="input">
        </div>
        <div>
            <label for="to" class="label">Hasta</label>
            <input id="to" type="date" wire:model.live="to" class="input">
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="preset(1)" class="btn-ghost">Hoy</button>
            <button type="button" wire:click="preset(7)" class="btn-ghost">7 días</button>
            <button type="button" wire:click="preset(30)" class="btn-ghost">30 días</button>
        </div>
    </div>

    @if ($error)
        <p class="mb-4 rounded-xl bg-salida/10 px-4 py-3 font-semibold text-salida" role="alert">{{ $error }}</p>
    @endif

    @if ($report)
        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-5"><p class="text-sm text-pizarra/70">Ventas</p><p class="text-3xl font-extrabold" data-testid="sales">{{ Money::format($totals['sales_cents']) }}</p></div>
            <div class="card p-5"><p class="text-sm text-pizarra/70">Órdenes cobradas</p><p class="text-3xl font-extrabold" data-testid="orders">{{ $totals['orders'] }}</p></div>
            <div class="card p-5"><p class="text-sm text-pizarra/70">Ticket promedio</p><p class="text-3xl font-extrabold">{{ Money::format($totals['average_ticket_cents']) }}</p></div>
            <div class="card p-5"><p class="text-sm text-pizarra/70">Propinas</p><p class="text-3xl font-extrabold">{{ Money::format($totals['tips_cents']) }}</p></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Ventas por día</h2>
                    <a href="{{ $csv('ventas') }}" class="btn-ghost min-h-9 px-3 py-1 text-sm" data-testid="csv-ventas">Exportar CSV</a>
                </header>
                <ul class="space-y-1.5">
                    @foreach ($byDay as $day)
                        <li class="grid grid-cols-[6.5rem_1fr_6.5rem] items-center gap-2 text-sm">
                            <span class="text-pizarra/70">{{ $day['label'] }}</span>
                            <span class="h-5 rounded bg-pizarra-50">
                                <span class="block h-full rounded bg-acento" style="width: {{ round($day['sales_cents'] * 100 / $maxDay, 1) }}%"></span>
                            </span>
                            <span class="text-right font-semibold">{{ Money::format($day['sales_cents']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card p-5">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Ventas por método de pago</h2>
                    <a href="{{ $csv('metodos') }}" class="btn-ghost min-h-9 px-3 py-1 text-sm">Exportar CSV</a>
                </header>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-pizarra/60">
                        <tr><th class="py-1">Método</th><th class="py-1 text-right">Pagos</th><th class="py-1 text-right">Ventas</th><th class="py-1 text-right">%</th><th class="py-1 text-right">Propinas</th></tr>
                    </thead>
                    <tbody class="divide-y divide-pizarra/10">
                        @foreach ($byMethod as $m)
                            <tr>
                                <td class="py-2 font-semibold">{{ $m['label'] }}</td>
                                <td class="py-2 text-right">{{ $m['count'] }}</td>
                                <td class="py-2 text-right font-semibold">{{ Money::format($m['sales_cents']) }}</td>
                                <td class="py-2 text-right">{{ round($m['sales_cents'] * 100 / $methodTotal) }}%</td>
                                <td class="py-2 text-right">{{ Money::format($m['tips_cents']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="card p-5">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Platillos más vendidos</h2>
                    <a href="{{ $csv('platillos') }}" class="btn-ghost min-h-9 px-3 py-1 text-sm">Exportar CSV</a>
                </header>
                @if ($topProducts->isEmpty())
                    <p class="py-6 text-center text-pizarra/60">Sin ventas en este rango.</p>
                @else
                    <ol class="divide-y divide-pizarra/10 text-sm">
                        @foreach ($topProducts as $i => $p)
                            <li class="flex items-center gap-3 py-2">
                                <span class="w-6 text-center font-extrabold text-pizarra/50">{{ $i + 1 }}</span>
                                <span class="flex-1 font-semibold">{{ $p['name'] }}</span>
                                <span class="w-20 text-right">{{ $p['quantity'] }} vendidos</span>
                                <span class="w-24 text-right font-semibold">{{ Money::format($p['sales_cents']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section class="card p-5">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Tiempos de servicio</h2>
                    <a href="{{ $csv('tiempos') }}" class="btn-ghost min-h-9 px-3 py-1 text-sm">Exportar CSV</a>
                </header>
                <p class="mb-3 text-sm text-pizarra/60">Promedios de {{ $times['orders'] }} {{ $times['orders'] === 1 ? 'orden cobrada' : 'órdenes cobradas' }}, calculados con el historial de estados.</p>
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-pizarra-50/70 p-4"><dt class="text-sm text-pizarra/70">Espera en cocina</dt><dd class="text-xl font-extrabold">{{ $fmtMin($times['wait']) }}</dd><dd class="text-xs text-pizarra/50">pendiente → en preparación</dd></div>
                    <div class="rounded-xl bg-pizarra-50/70 p-4"><dt class="text-sm text-pizarra/70">Preparación</dt><dd class="text-xl font-extrabold" data-testid="avg-prep">{{ $fmtMin($times['prep']) }}</dd><dd class="text-xs text-pizarra/50">en preparación → lista</dd></div>
                    <div class="rounded-xl bg-pizarra-50/70 p-4"><dt class="text-sm text-pizarra/70">Tiempo total</dt><dd class="text-xl font-extrabold" data-testid="avg-total">{{ $fmtMin($times['total']) }}</dd><dd class="text-xs text-pizarra/50">pendiente → cobrada</dd></div>
                </dl>
            </section>
        </div>
    @endif
</div>
