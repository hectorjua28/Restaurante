<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pendiente = 'pendiente';
    case EnPreparacion = 'en_preparacion';
    case Lista = 'lista';
    case Entregada = 'entregada';
    case Cobrada = 'cobrada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnPreparacion => 'En preparación',
            self::Lista => 'Lista',
            self::Entregada => 'Entregada',
            self::Cobrada => 'Cobrada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Clave de color compartida entre la web y la app. */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'amarillo',
            self::EnPreparacion => 'azul',
            self::Lista => 'verde',
            self::Entregada => 'pizarra',
            self::Cobrada => 'morado',
            self::Cancelada => 'gris',
        };
    }

    /** Siguiente estado del flujo lineal (sin saltos ni retrocesos). */
    public function next(): ?self
    {
        return match ($this) {
            self::Pendiente => self::EnPreparacion,
            self::EnPreparacion => self::Lista,
            self::Lista => self::Entregada,
            self::Entregada => self::Cobrada,
            self::Cobrada, self::Cancelada => null,
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($target === self::Cancelada) {
            return $this === self::Pendiente;
        }

        return $this->next() === $target;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cobrada, self::Cancelada], true);
    }

    /**
     * Estados que mantienen ocupada una mesa.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pendiente, self::EnPreparacion, self::Lista, self::Entregada];
    }

    /**
     * Estados visibles en el tablero de cocina.
     *
     * @return list<self>
     */
    public static function kitchen(): array
    {
        return [self::Pendiente, self::EnPreparacion, self::Lista];
    }
}
