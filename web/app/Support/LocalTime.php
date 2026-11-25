<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Las fechas se guardan en UTC y se convierten a la hora local solo al mostrarse.
 */
final class LocalTime
{
    public static function zone(): string
    {
        return config('app.display_timezone');
    }

    public static function format(?CarbonInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return $date ? $date->copy()->setTimezone(self::zone())->format($format) : '—';
    }

    public static function now(): CarbonInterface
    {
        return now()->setTimezone(self::zone());
    }

    /**
     * Inicio y fin (en UTC) del día local indicado.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function dayRangeUtc(?CarbonInterface $localDay = null): array
    {
        $day = ($localDay ?? self::now())->copy()->setTimezone(self::zone());

        return [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()];
    }
}
