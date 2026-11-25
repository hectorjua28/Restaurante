<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="mb-4 text-2xl font-bold">Categorías</h1>
    <x-flash :message="$flash" :type="$flashType" />

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card overflow-hidden lg:col-span-2">
            <table class="w-full text-left text-sm">
                <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                    <tr>
                        <th class="px-4 py-3">Orden</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Platillos</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pizarra/10">
                    @forelse ($categories as $category)
                        <tr wire:key="cat-{{ $category->id }}" @class(['bg-acento/10' => $editingId === $category->id])>
                            <td class="px-4 py-3">{{ $category->position }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $category->name }}</td>
                            <td class="px-4 py-3">{{ $category->products_count }}</td>
                            <td class="px-4 py-3">
                                <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-st-lista/15 text-green-800' => $category->active, 'bg-st-cancelada/20 text-pizarra/70' => ! $category->active])>
                                    {{ $category->active ? 'Activa' : 'Oculta' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $category->id }})" class="font-semibold text-st-preparacion hover:underline">Editar</button>
                                <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="¿Eliminar la categoría «{{ $category->name }}»?" class="ml-3 font-semibold text-salida hover:underline">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-pizarra/60">No hay categorías.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-bold">{{ $editingId ? 'Editar categoría' : 'Nueva categoría' }}</h2>
            <form wire:submit="save" class="space-y-3" novalidate>
                <div>
                    <label for="name" class="label">Nombre</label>
                    <input id="name" type="text" wire:model="name" class="input" maxlength="80">
                    @error('name') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="position" class="label">Orden en el menú</label>
                    <input id="position" type="number" min="0" wire:model="position" class="input">
                    @error('position') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <label class="flex min-h-11 items-center gap-2 font-semibold">
                    <input type="checkbox" wire:model="active" class="size-5 accent-pizarra"> Visible en la app
                </label>
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
