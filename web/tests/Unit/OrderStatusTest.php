<?php

use App\Enums\OrderStatus;

it('sigue el flujo lineal pendiente → en preparación → lista → entregada → cobrada', function () {
    expect(OrderStatus::Pendiente->next())->toBe(OrderStatus::EnPreparacion)
        ->and(OrderStatus::EnPreparacion->next())->toBe(OrderStatus::Lista)
        ->and(OrderStatus::Lista->next())->toBe(OrderStatus::Entregada)
        ->and(OrderStatus::Entregada->next())->toBe(OrderStatus::Cobrada)
        ->and(OrderStatus::Cobrada->next())->toBeNull()
        ->and(OrderStatus::Cancelada->next())->toBeNull();
});

it('no permite saltar ni regresar estados', function (OrderStatus $from, OrderStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'saltar a lista' => [OrderStatus::Pendiente, OrderStatus::Lista],
    'saltar a cobrada' => [OrderStatus::Lista, OrderStatus::Cobrada],
    'regresar a pendiente' => [OrderStatus::EnPreparacion, OrderStatus::Pendiente],
    'regresar a preparación' => [OrderStatus::Lista, OrderStatus::EnPreparacion],
    'modificar cobrada' => [OrderStatus::Cobrada, OrderStatus::Entregada],
    'cancelar en preparación' => [OrderStatus::EnPreparacion, OrderStatus::Cancelada],
    'cancelar cobrada' => [OrderStatus::Cobrada, OrderStatus::Cancelada],
]);

it('solo se cancela desde pendiente', function () {
    expect(OrderStatus::Pendiente->canTransitionTo(OrderStatus::Cancelada))->toBeTrue();
});

it('marca cobrada y cancelada como estados finales', function () {
    expect(OrderStatus::Cobrada->isFinal())->toBeTrue()
        ->and(OrderStatus::Cancelada->isFinal())->toBeTrue()
        ->and(OrderStatus::Entregada->isFinal())->toBeFalse();
});

it('tiene etiqueta y color para cada estado', function () {
    foreach (OrderStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty()
            ->and($status->color())->not->toBeEmpty();
    }
});
