<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <a href="{{ route('admin.products.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 font-semibold text-st-preparacion hover:underline">← Volver a platillos</a>
    <h1 class="mb-4 text-2xl font-bold">{{ $product ? 'Editar platillo' : 'Nuevo platillo' }}</h1>

    <form wire:submit="save" class="grid gap-4 lg:grid-cols-3" novalidate>
        <section class="card space-y-4 p-5 lg:col-span-2">
            <div>
                <label for="name" class="label">Nombre</label>
                <input id="name" type="text" wire:model="name" class="input" maxlength="120" required>
                @error('name') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="label">Descripción <span class="font-normal text-pizarra/50">(opcional)</span></label>
                <textarea id="description" wire:model="description" rows="2" class="input" maxlength="500"></textarea>
                @error('description') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="categoryId" class="label">Categoría</label>
                    <select id="categoryId" wire:model="categoryId" class="input">
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="price" class="label">Precio (MXN)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-pizarra/50">$</span>
                        <input id="price" type="text" inputmode="decimal" wire:model="price" class="input pl-7" placeholder="45.00">
                    </div>
                    @error('price') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="station" class="label">Estación</label>
                    <select id="station" wire:model="station" class="input">
                        @foreach ($stations as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex min-h-11 items-center gap-2 font-semibold">
                <input type="checkbox" wire:model="available" class="size-5 accent-pizarra">
                Disponible para nuevas comandas
            </label>
        </section>

        <section class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold">Modificadores</h2>
                <button type="button" wire:click="addModifier" class="btn-ghost min-h-9 px-3 py-1 text-sm">+ Agregar</button>
            </div>
            <p class="mb-3 text-sm text-pizarra/60">Opciones como «sin cebolla» o «término medio». El precio extra puede ser 0.</p>

            <div class="space-y-3">
                @forelse ($modifiers as $i => $modifier)
                    <div wire:key="mod-{{ $i }}-{{ $modifier['id'] ?? 'nuevo' }}" class="rounded-xl bg-pizarra-50/60 p-3">
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label for="mod-name-{{ $i }}" class="sr-only">Nombre del modificador</label>
                                <input id="mod-name-{{ $i }}" type="text" wire:model="modifiers.{{ $i }}.name" class="input" placeholder="Ej. Sin cebolla" maxlength="80">
                            </div>
                            <div class="w-28">
                                <label for="mod-price-{{ $i }}" class="sr-only">Precio extra</label>
                                <input id="mod-price-{{ $i }}" type="text" inputmode="decimal" wire:model="modifiers.{{ $i }}.price" class="input" placeholder="+$0">
                            </div>
                            <button type="button" wire:click="removeModifier({{ $i }})" class="btn min-h-11 px-3 text-salida hover:bg-salida/10" aria-label="Quitar modificador">✕</button>
                        </div>
                        @error("modifiers.$i.name") <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                        @error("modifiers.$i.price") <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-pizarra/20 px-3 py-6 text-center text-sm text-pizarra/60">Sin modificadores.</p>
                @endforelse
            </div>
        </section>

        <div class="flex justify-end gap-3 lg:col-span-3">
            <a href="{{ route('admin.products.index') }}" wire:navigate class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Guardar platillo</button>
        </div>
    </form>
</div>
