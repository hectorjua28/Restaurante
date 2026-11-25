<?php

namespace App\Enums;

enum TableStatus: string
{
    case Libre = 'libre';
    case Ocupada = 'ocupada';
    case PorCobrar = 'por_cobrar';

    public function label(): string
    {
        return match ($this) {
            self::Libre => 'Libre',
            self::Ocupada => 'Ocupada',
            self::PorCobrar => 'Por cobrar',
        };
    }
}
