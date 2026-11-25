@php
    use App\Support\Money;
@endphp

<div>
    <x-slot:nav>
        <x-kitchen-nav />
    </x-slot:nav>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Platillos agotados</h1>
            <p class="text-sm text-pizarra/70">
                Un platillo agotado no se puede agregar a comandas nuevas. Las órdenes ya enviadas no cambian.
            </p>
        </div>
        <span class="rounded-full bg-salida/10 px-3 py-1 text-sm font-bold text-salida" data-testid="sold-out-count">
            {{ $soldOutCount }} {{ $soldOutCount === 1 ? 'agotado' : 'agotados' }}
        </span>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="search">Buscar platillo</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar platillo…" class="input max-w-sm">
        <label class="flex min-h-11 items-center gap-2 font-medium">
            <input type="checkbox" wire:model.live="onlySoldOut" class="size-5 accent-pizarra">
            Ver solo agotados
        </label>
    </div>

    @if ($flash !== '')
        <div wire:key="flash-{{ md5($flash) }}" role="status" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)"
            x-show="show" class="mb-4 rounded-xl bg-st-lista/15 px-4 py-3 font-semibold text-green-800">
            {{ $flash }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($categories as $category)
            <section class="card overflow-hidden" wire:key="cat-{{ $category->id }}">
                <h2 class="bg-pizarra px-4 py-2 font-bold text-white">{{ $category->name }}</h2>
                <ul class="divide-y divide-pizarra/10">
                    @foreach ($category->products as $product)
                        <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="product-{{ $product->id }}">
                            <div>
                                <p class="flex flex-wrap items-center gap-2 font-semibold">
                                    <span @class(['line-through text-pizarra/50' => ! $product->available])>{{ $product->name }}</span>
                                    @if ($product->available)
                                        <span class="rounded-full bg-st-lista/15 px-2 py-0.5 text-xs font-bold text-green-800">Disponible</span>
                                    @else
                                        <span class="rounded-full bg-salida px-2 py-0.5 text-xs font-bold text-white">Agotado</span>
                                    @endif
                                </p>
                                <p class="text-sm text-pizarra/60">{{ Money::format($product->price_cents) }} · {{ $product->station->label() }}</p>
                            </div>
                            <button type="button" wire:click="toggle({{ $product->id }})" wire:loading.attr="disabled"
                                wire:target="toggle({{ $product->id }})"
                                @class([
                                    'btn min-w-40 shrink-0',
                                    'bg-white text-salida ring-2 ring-salida/60 hover:bg-salida/5' => $product->available,
                                    'bg-st-lista text-white hover:brightness-110' => ! $product->available,
                                ])
                                data-testid="toggle-{{ $product->id }}">
                                {{ $product->available ? 'Marcar agotado' : 'Marcar disponible' }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="col-span-full py-10 text-center text-pizarra/60">No hay platillos que coincidan.</p>
        @endforelse
    </div>
</div>
