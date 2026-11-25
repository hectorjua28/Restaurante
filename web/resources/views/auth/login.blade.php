<x-layouts.guest title="Iniciar sesión">
    <div class="card w-full max-w-sm overflow-hidden">
        <div class="flex flex-col items-center gap-2 bg-pizarra px-6 py-6 text-white">
            <img src="{{ asset('img/logo.png') }}" alt="Logo de Antojería Pingüin" class="size-20 rounded-full bg-white object-contain p-1">
            <h1 class="text-xl font-bold">Antojería Pingüin</h1>
            <p class="text-sm text-white/70">Cocina y administración</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4 p-6" novalidate>
            @csrf

            @if ($errors->any())
                <div role="alert" class="rounded-xl bg-salida/10 px-3 py-2 text-sm font-medium text-salida">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="email" class="label">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            </div>

            <div>
                <label for="password" class="label">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-pizarra">
                Mantener sesión iniciada
            </label>

            <button type="submit" class="btn-primary w-full">Entrar</button>
        </form>
    </div>
</x-layouts.guest>
