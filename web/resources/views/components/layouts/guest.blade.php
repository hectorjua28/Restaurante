<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Iniciar sesión' }} · Antojería Pingüin</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-fondo font-sans text-pizarra antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        {{ $slot }}
    </main>
</body>
</html>
