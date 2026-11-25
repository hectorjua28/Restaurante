@php
    use App\Support\LocalTime;

    $columnStyles = [
        'pendiente' => ['bar' => 'bg-st-pendiente', 'text' => 'text-pizarra', 'button' => 'btn-primary', 'action' => 'Empezar a preparar'],
        'en_preparacion' => ['bar' => 'bg-st-preparacion', 'text' => 'text-white', 'button' => 'btn bg-st-preparacion text-white hover:brightness-110', 'action' => 'Marcar como lista'],
        'lista' => ['bar' => 'bg-st-lista', 'text' => 'text-white', 'button' => null, 'action' => null],
    ];
@endphp

<div wire:poll.4s>
    <x-slot:nav>
        <x-kitchen-nav />
    </x-slot:nav>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Tablero de cocina</h1>
            <p class="text-sm text-pizarra/70">
                Se actualiza cada 4 segundos · Última actualización:
                <span class="font-semibold">{{ LocalTime::format($now, 'H:i:s') }}</span>
            </p>
        </div>

        <div class="flex rounded-xl bg-white p-1 ring-1 ring-pizarra/10" role="group" aria-label="Filtrar por estación">
            @foreach (['' => 'Todas', 'cocina' => 'Cocina', 'barra' => 'Barra'] as $value => $label)
                <button type="button" wire:click="setStation('{{ $value }}')"
                    @class([
                        'min-h-11 rounded-lg px-4 font-semibold transition',
                        'bg-pizarra text-white' => $station === $value,
                        'text-pizarra hover:bg-pizarra-50' => $station !== $value,
                    ])
                    aria-pressed="{{ $station === $value ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($flash !== '')
        <div wire:key="flash-{{ md5($flash.$flashType) }}" role="status"
            x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
            @class([
                'mb-4 rounded-xl px-4 py-3 font-semibold',
                'bg-st-lista/15 text-green-800' => $flashType === 'success',
                'bg-salida/10 text-salida' => $flashType === 'error',
            ])>
            {{ $flash }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($columns as $key => $column)
            @php($style = $columnStyles[$key])
            <section class="flex min-h-64 flex-col rounded-2xl bg-pizarra-50/60 ring-1 ring-pizarra/10" aria-labelledby="col-{{ $key }}">
                <header class="{{ $style['bar'] }} {{ $style['text'] }} flex items-center justify-between rounded-t-2xl px-4 py-3">
                    <h2 id="col-{{ $key }}" class="text-lg font-bold">{{ $column['status']->label() }}</h2>
                    <span class="rounded-full bg-white/90 px-3 py-0.5 text-sm font-bold text-pizarra" data-testid="count-{{ $key }}">
                        {{ $column['orders']->count() }}
                    </span>
                </header>

                <div class="flex flex-1 flex-col gap-3 p-3">
                    @forelse ($column['orders'] as $order)
                        @php($minutes = (int) $order->created_at->diffInMinutes($now))
                        <article wire:key="order-{{ $order->id }}" class="card overflow-hidden" data-testid="order-{{ $order->id }}">
                            <div class="flex items-start justify-between gap-2 border-b border-pizarra/10 px-4 py-3">
                                <div>
                                    <p class="text-2xl font-extrabold leading-none">Mesa {{ $order->diningTable->number }}</p>
                                    <p class="mt-1 text-sm text-pizarra/70">#{{ $order->id }} · {{ $order->waiter->name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold">{{ LocalTime::format($order->created_at, 'H:i') }}</p>
                                    <p @class([
                                        'mt-1 inline-block rounded-full px-2 py-0.5 text-sm font-bold',
                                        'bg-pizarra-50 text-pizarra' => $minutes < 15,
                                        'bg-acento/30 text-pizarra' => $minutes >= 15 && $minutes < 25,
                                        'bg-salida text-white' => $minutes >= 25,
                                    ]) title="Tiempo desde que se envió la comanda">
                                        {{ $minutes < 1 ? 'menos de 1 min' : ($minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' min') }}
                                    </p>
                                </div>
                            </div>

                            <ul class="divide-y divide-pizarra/5 px-4">
                                @foreach ($order->items as $item)
                                    <li @class(['py-2', 'opacity-35' => $stationEnum && $item->station !== $stationEnum])>
                                        <div class="flex items-baseline gap-2">
                                            <span class="min-w-8 text-xl font-extrabold">{{ $item->quantity }}×</span>
                                            <span class="text-lg font-semibold">{{ $item->product_name }}</span>
                                            <span class="ml-auto rounded bg-pizarra-50 px-1.5 text-xs font-semibold uppercase text-pizarra/70">{{ $item->station->label() }}</span>
                                        </div>
                                        @if (! empty($item->modifiers))
                                            <p class="ml-10 text-sm font-medium text-st-preparacion">
                                                {{ collect($item->modifiers)->pluck('name')->join(' · ') }}
                                            </p>
                                        @endif
                                        @if ($item->note)
                                            <p class="ml-10 mt-1 rounded-lg bg-acento/25 px-2 py-1 text-sm font-bold">
                                                Nota: {{ $item->note }}
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <div class="p-3">
                                @if ($style['action'])
                                    <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled"
                                        wire:target="advance({{ $order->id }})"
                                        class="{{ $style['button'] }} w-full min-h-14 text-lg" data-testid="advance-{{ $order->id }}">
                                        {{ $style['action'] }}
                                    </button>
                                @else
                                    <p class="rounded-xl bg-st-lista/15 px-3 py-3 text-center font-semibold text-green-800">
                                        Esperando a que el mesero la entregue
                                    </p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="py-10 text-center text-pizarra/50">Sin órdenes</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
