<?php

namespace App\Enums;

enum Station: string
{
    case Cocina = 'cocina';
    case Barra = 'barra';

    public function label(): string
    {
        return match ($this) {
            self::Cocina => 'Cocina',
            self::Barra => 'Barra',
        };
    }
}
