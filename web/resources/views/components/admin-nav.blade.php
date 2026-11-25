@php
    $links = [
        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Resumen'],
        ['route' => 'admin.orders.index', 'match' => 'admin.orders.*', 'label' => 'Órdenes'],
        ['route' => 'admin.payments', 'match' => 'admin.payments', 'label' => 'Pagos'],
        ['route' => 'admin.products.index', 'match' => 'admin.products.*', 'label' => 'Platillos'],
        ['route' => 'admin.categories', 'match' => 'admin.categories', 'label' => 'Categorías'],
        ['route' => 'admin.tables', 'match' => 'admin.tables', 'label' => 'Mesas'],
        ['route' => 'admin.users', 'match' => 'admin.users', 'label' => 'Usuarios'],
        ['route' => 'admin.cash', 'match' => 'admin.cash', 'label' => 'Corte de caja'],
        ['route' => 'admin.reports', 'match' => 'admin.reports*', 'label' => 'Reportes'],
        ['route' => 'admin.audit', 'match' => 'admin.audit', 'label' => 'Bitácora'],
    ];
@endphp

@foreach ($links as $link)
    @php($active = request()->routeIs($link['match']))
    <a href="{{ route($link['route']) }}" wire:navigate
        @class([
            'rounded-lg px-3 py-2 font-semibold transition',
            'bg-acento text-pizarra' => $active,
            'text-white/85 hover:bg-white/10' => ! $active,
        ])
        @if ($active) aria-current="page" @endif>
        {{ $link['label'] }}
    </a>
@endforeach

<a href="{{ route('kitchen.board') }}" class="rounded-lg px-3 py-2 font-semibold text-white/85 hover:bg-white/10">Cocina</a>
