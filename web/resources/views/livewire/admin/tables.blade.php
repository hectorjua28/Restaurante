<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="mb-4 text-2xl font-bold">Mesas</h1>
    <x-flash :message="$flash" :type="$flashType" />

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @forelse ($tables as $table)
                    <article wire:key="table-{{ $table->id }}" @class(['card p-4', 'ring-2 ring-acento' => $editingId === $table->id])>
                        <div class="flex items-start justify-between">
                            <p class="text-3xl font-extrabold">{{ $table->number }}</p>
                            <x-status-badge :status="$table->status" />
                        </div>
                        <p class="mt-1 text-sm text-pizarra/70">{{ $table->capacity }} personas · {{ $table->active_orders_count }} {{ $table->active_orders_count === 1 ? 'orden activa' : 'órdenes activas' }}</p>
                        <div class="mt-3 flex gap-3 text-sm">
                            <button type="button" wire:click="edit({{ $table->id }})" class="font-semibold text-st-preparacion hover:underline">Editar</button>
                            <button type="button" wire:click="delete({{ $table->id }})" wire:confirm="¿Eliminar la mesa {{ $table->number }}?" class="font-semibold text-salida hover:underline">Eliminar</button>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full py-10 text-center text-pizarra/60">No hay mesas.</p>
                @endforelse
            </div>
        </section>

        <section class="card h-fit p-5">
            <h2 class="mb-3 font-bold">{{ $editingId ? 'Editar mesa' : 'Nueva mesa' }}</h2>
            <form wire:submit="save" class="space-y-3" novalidate>
                <div>
                    <label for="number" class="label">Número</label>
                    <input id="number" type="number" min="1" wire:model="number" class="input">
                    @error('number') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="capacity" class="label">Capacidad (personas)</label>
                    <input id="capacity" type="number" min="1" wire:model="capacity" class="input">
                    @error('capacity') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="btn-ghost flex-1">Cancelar</button>
                    @endif
                    <button type="submit" class="btn-primary flex-1">Guardar</button>
                </div>
            </form>
        </section>
    </div>
</div>
