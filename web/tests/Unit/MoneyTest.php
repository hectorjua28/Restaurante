<?php

use App\Support\Money;

it('formatea centavos como pesos mexicanos', function (int $cents, string $expected) {
    expect(Money::format($cents))->toBe($expected);
})->with([
    [0, '$0.00'],
    [5, '$0.05'],
    [4550, '$45.50'],
    [123456789, '$1,234,567.89'],
    [-2000, '-$20.00'],
]);

it('trata null como cero para nunca mostrar "null"', function () {
    expect(Money::format(null))->toBe('$0.00');
});

it('convierte montos en texto a centavos sin usar float', function (string $amount, int $cents) {
    expect(Money::toCents($amount))->toBe($cents);
})->with([
    ['0', 0],
    ['45', 4500],
    ['45.5', 4550],
    ['0.1', 10],
    ['19.99', 1999],
    ['-3.05', -305],
]);

it('rechaza montos con más de dos decimales o con texto', function (string $amount) {
    Money::toCents($amount);
})->with(['1.999', 'abc', '1,5', ''])->throws(InvalidArgumentException::class);
