@php
    use App\Support\Money;
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Platillos</h1>
        <a href="{{ route('admin.products.create') }}" wire:navigate class="btn-primary">+ Nuevo platillo</a>
    </div>

    <div class="card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="search" class="label">Buscar</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nombre del platillo">
        </div>
        <div class="min-w-48">
            <label for="category" class="label">Categoría</label>
            <select id="category" wire:model.live="category" class="input">
                <option value="">Todas</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-flash :message="$flash" />

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                <tr>
                    <th class="px-4 py-3">Platillo</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3">Estación</th>
                    <th class="px-4 py-3 text-right">Precio</th>
                    <th class="px-4 py-3">Disponibilidad</th>
                    <th class="px-4 py-3 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pizarra/10">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $product->name }}</p>
                            @if ($product->modifiers->isNotEmpty())
                                <p class="text-xs text-pizarra/60">{{ $product->modifiers->pluck('name')->join(' · ') }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $product->category->name }}</td>
                        <td class="px-4 py-3">{{ $product->station->label() }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ Money::format($product->price_cents) }}</td>
                        <td class="px-4 py-3">
                            <button type="button" wire:click="toggle({{ $product->id }})"
                                @class([
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-st-lista/15 text-green-800' => $product->available,
                                    'bg-salida text-white' => ! $product->available,
                                ])
                                title="Cambiar disponibilidad">
                                {{ $product->available ? 'Disponible' : 'Agotado' }}
                            </button>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="font-semibold text-st-preparacion hover:underline">Editar</a>
                            <button type="button" wire:click="delete({{ $product->id }})"
                                wire:confirm="¿Eliminar «{{ $product->name }}» del menú? Las órdenes anteriores no cambian."
                                class="ml-3 font-semibold text-salida hover:underline">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-pizarra/60">No hay platillos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</div>
