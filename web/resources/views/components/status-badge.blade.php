@props(['status'])

@php
    // Colores de estado consistentes en todo el sistema (iguales a la app móvil).
    $classes = match ($status->value) {
        'pendiente' => 'bg-st-pendiente text-pizarra',
        'en_preparacion' => 'bg-st-preparacion text-white',
        'lista' => 'bg-st-lista text-white',
        'entregada' => 'bg-st-entregada text-white',
        'cobrada' => 'bg-st-cobrada text-white',
        'cancelada' => 'bg-st-cancelada text-white',
        'libre' => 'bg-st-lista text-white',
        'ocupada' => 'bg-st-preparacion text-white',
        'por_cobrar' => 'bg-st-pendiente text-pizarra',
        default => 'bg-pizarra-50 text-pizarra',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold $classes"]) }}>
    {{ $status->label() }}
</span>
