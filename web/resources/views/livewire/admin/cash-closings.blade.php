@php
    use App\Support\LocalTime;
    use App\Support\Money;
    $diffClass = fn (?int $d) => match (true) {
        $d === null => 'text-pizarra/50',
        $d === 0 => 'text-green-700',
        $d > 0 => 'text-st-preparacion',
        default => 'text-salida',
    };
    $diffText = fn (?int $d) => match (true) {
        $d === null => '—',
        $d === 0 => 'Cuadra',
        $d > 0 => 'Sobran '.Money::format($d),
        default => 'Faltan '.Money::format(-$d),
    };
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="text-2xl font-bold">Corte de caja</h1>
    <p class="mb-4 text-sm text-pizarra/70">
        Incluye todos los pagos desde el último corte
        @if ($current['period_start'])
            ({{ LocalTime::format($current['period_start']) }}).
        @else
            (todavía no hay cortes).
        @endif
    </p>

    <x-flash :message="$flash" :type="$flashType" />

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-3 font-bold">Turno actual · {{ $current['payments_count'] }} {{ $current['payments_count'] === 1 ? 'pago' : 'pagos' }}</h2>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-pizarra-50/70 p-4">
                    <dt class="text-sm text-pizarra/70">Efectivo</dt>
                    <dd class="text-2xl font-extrabold" data-testid="cash">{{ Money::format($current['cash_cents']) }}</dd>
                </div>
                <div class="rounded-xl bg-pizarra-50/70 p-4">
                    <dt class="text-sm text-pizarra/70">Tarjeta</dt>
                    <dd class="text-2xl font-extrabold" data-testid="card">{{ Money::format($current['card_cents']) }}</dd>
                </div>
                <div class="rounded-xl bg-pizarra-50/70 p-4">
                    <dt class="text-sm text-pizarra/70">Transferencia</dt>
                    <dd class="text-2xl font-extrabold" data-testid="transfer">{{ Money::format($current['transfer_cents']) }}</dd>
                </div>
            </dl>
            <div class="mt-4 grid gap-2 border-t border-pizarra/10 pt-4 text-sm sm:grid-cols-2">
                <p>Ventas totales: <span class="font-bold">{{ Money::format($current['sales_cents']) }}</span></p>
                <p>Propinas (todas): <span class="font-bold">{{ Money::format($current['tips_cents']) }}</span></p>
                <p>Propinas en efectivo: <span class="font-bold">{{ Money::format($current['cash_tips_cents']) }}</span></p>
                <p class="text-base">Efectivo esperado en caja: <span class="font-extrabold" data-testid="expected">{{ Money::format($current['expected_cash_cents']) }}</span></p>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-bold">Hacer corte</h2>
            <form wire:submit="close" class="space-y-3" novalidate>
                <div>
                    <label for="counted" class="label">Efectivo contado en caja</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-pizarra/50">$</span>
                        <input id="counted" type="text" inputmode="decimal" wire:model.live.debounce.300ms="counted" class="input pl-7" placeholder="0.00">
                    </div>
                    @error('counted') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <p class="rounded-xl bg-pizarra-50/70 px-3 py-2 text-sm">
                    Diferencia: <span class="font-bold {{ $diffClass($previewDiff) }}" data-testid="preview-diff">{{ $diffText($previewDiff) }}</span>
                </p>
                <div>
                    <label for="notes" class="label">Notas <span class="font-normal text-pizarra/50">(opcional)</span></label>
                    <textarea id="notes" wire:model="notes" rows="2" class="input" maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn-primary w-full" @disabled($current['payments_count'] === 0)
                    wire:confirm="¿Hacer el corte de caja? Los pagos incluidos ya no se podrán cambiar de corte.">
                    Hacer corte de caja
                </button>
                @if ($current['payments_count'] === 0)
                    <p class="text-center text-sm text-pizarra/60">No hay pagos desde el último corte.</p>
                @endif
            </form>
        </section>
    </div>

    <section class="card mt-6 overflow-x-auto">
        <h2 class="border-b border-pizarra/10 px-5 py-3 font-bold">Cortes anteriores</h2>
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                <tr>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3">Realizó</th>
                    <th class="px-4 py-3 text-right">Efectivo</th>
                    <th class="px-4 py-3 text-right">Tarjeta</th>
                    <th class="px-4 py-3 text-right">Transf.</th>
                    <th class="px-4 py-3 text-right">Propinas</th>
                    <th class="px-4 py-3 text-right">Esperado</th>
                    <th class="px-4 py-3 text-right">Contado</th>
                    <th class="px-4 py-3 text-right">Diferencia</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pizarra/10">
                @forelse ($closings as $c)
                    <tr wire:key="closing-{{ $c->id }}">
                        <td class="px-4 py-3">
                            {{ $c->period_start ? LocalTime::format($c->period_start, 'd/m H:i') : 'Inicio' }} – {{ LocalTime::format($c->period_end, 'd/m H:i') }}
                            @if ($c->notes)<p class="text-xs text-pizarra/60">{{ $c->notes }}</p>@endif
                        </td>
                        <td class="px-4 py-3">{{ $c->user->name }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($c->cash_cents) }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($c->card_cents) }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($c->transfer_cents) }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($c->tips_cents) }}</td>
                        <td class="px-4 py-3 text-right">{{ Money::format($c->expected_cash_cents) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ Money::format($c->counted_cash_cents) }}</td>
                        <td class="px-4 py-3 text-right font-bold {{ $diffClass($c->difference_cents) }}">{{ $diffText($c->difference_cents) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-pizarra/60">Todavía no hay cortes de caja.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $closings->links() }}</div>
</div>
