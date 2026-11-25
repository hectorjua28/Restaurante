<?php

namespace App\Support;

/**
 * El dinero se maneja siempre en centavos enteros; solo se formatea al mostrarlo.
 */
final class Money
{
    public static function format(?int $cents): string
    {
        $cents ??= 0;
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.'$'.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Convierte "123.45" a 12345 sin pasar por float. */
    public static function toCents(string|int $amount): int
    {
        $amount = trim((string) $amount);
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException("Monto inválido: {$amount}");
        }
        $negative = str_starts_with($amount, '-');
        [$int, $dec] = array_pad(explode('.', ltrim($amount, '-')), 2, '0');
        $cents = ((int) $int) * 100 + (int) str_pad($dec, 2, '0');

        return $negative ? -$cents : $cents;
    }
}
