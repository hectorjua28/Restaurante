<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel' }} · Antojería Pingüin</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-fondo font-sans text-pizarra antialiased">
    <header class="bg-pizarra text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route(auth()->user()->role->homeRoute()) }}" class="flex items-center gap-3">
                <img src="{{ asset('img/logo.png') }}" alt="" class="size-10 rounded-full bg-white object-contain p-0.5">
                <span class="text-lg font-bold leading-tight">Antojería Pingüin</span>
            </a>

            @isset($nav)
                <nav class="flex flex-1 flex-wrap items-center gap-1 text-sm">{{ $nav }}</nav>
            @else
                <div class="flex-1"></div>
            @endisset

            <div class="flex items-center gap-3 text-sm">
                <span class="hidden sm:inline">{{ auth()->user()->name }} · <span class="text-acento">{{ auth()->user()->role->label() }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-danger min-h-9 px-3 py-1 text-sm">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>

    {{-- Aviso de "sin conexión": se muestra si el polling deja de recibir respuestas del servidor. --}}
    <div id="offline-banner" role="status" aria-live="polite" hidden
        class="sticky top-0 z-50 bg-salida px-4 py-2 text-center font-semibold text-white">
        Sin conexión con el servidor. Reintentando…
    </div>

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
