@php
    $links = [
        ['route' => 'kitchen.board', 'label' => 'Tablero'],
        ['route' => 'kitchen.availability', 'label' => 'Platillos agotados'],
    ];
@endphp

@foreach ($links as $link)
    <a href="{{ route($link['route']) }}" wire:navigate
        @class([
            'rounded-lg px-3 py-2 font-semibold transition',
            'bg-acento text-pizarra' => request()->routeIs($link['route']),
            'text-white/85 hover:bg-white/10' => ! request()->routeIs($link['route']),
        ])
        @if (request()->routeIs($link['route'])) aria-current="page" @endif>
        {{ $link['label'] }}
    </a>
@endforeach

@if (auth()->user()->isAdmin())
    <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 font-semibold text-white/85 hover:bg-white/10">
        Administración
    </a>
@endif
