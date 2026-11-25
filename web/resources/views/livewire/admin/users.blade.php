<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <h1 class="mb-4 text-2xl font-bold">Usuarios</h1>
    <x-flash :message="$flash" :type="$flashType" />

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card overflow-x-auto lg:col-span-2">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="bg-pizarra-50 text-xs uppercase tracking-wide text-pizarra/70">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pizarra/10">
                    @foreach ($users as $user)
                        <tr wire:key="user-{{ $user->id }}" @class(['bg-acento/10' => $editingId === $user->id, 'text-pizarra/50' => ! $user->active])>
                            <td class="px-4 py-3 font-semibold">{{ $user->name }} @if ($user->id === auth()->id()) <span class="text-xs font-normal">(tú)</span> @endif</td>
                            <td class="px-4 py-3">{{ $user->email }}</td>
                            <td class="px-4 py-3">{{ $user->role->label() }}</td>
                            <td class="px-4 py-3">
                                <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-st-lista/15 text-green-800' => $user->active, 'bg-st-cancelada/20 text-pizarra/70' => ! $user->active])>
                                    {{ $user->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $user->id }})" class="font-semibold text-st-preparacion hover:underline">Editar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="card h-fit p-5">
            <h2 class="mb-3 font-bold">{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
            <form wire:submit="save" class="space-y-3" novalidate>
                <div>
                    <label for="name" class="label">Nombre</label>
                    <input id="name" type="text" wire:model="name" class="input" maxlength="100" autocomplete="off">
                    @error('name') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="label">Correo electrónico</label>
                    <input id="email" type="email" wire:model="email" class="input" autocomplete="off">
                    @error('email') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="role" class="label">Rol</label>
                    <select id="role" wire:model="role" class="input">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="password" class="label">Contraseña @if ($editingId)<span class="font-normal text-pizarra/50">(déjala vacía para no cambiarla)</span>@endif</label>
                    <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
                    @error('password') <p class="mt-1 text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-pizarra/60">Mínimo 8 caracteres, con letras y números.</p>
                </div>
                <label class="flex min-h-11 items-center gap-2 font-semibold">
                    <input type="checkbox" wire:model="active" class="size-5 accent-pizarra"> Activo (puede iniciar sesión)
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
