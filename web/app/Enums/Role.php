<?php

namespace App\Enums;

enum Role: string
{
    case Mesero = 'mesero';
    case Cocina = 'cocina';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Mesero => 'Mesero',
            self::Cocina => 'Cocina',
            self::Admin => 'Administrador',
        };
    }

    /** Ruta de inicio en el sitio web según el rol. */
    public function homeRoute(): ?string
    {
        return match ($this) {
            self::Cocina => 'kitchen.board',
            self::Admin => 'admin.dashboard',
            self::Mesero => null,
        };
    }
}
