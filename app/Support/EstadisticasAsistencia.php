<?php

namespace App\Support;

use Carbon\Carbon;

class EstadisticasAsistencia
{
    /** Minutos → "dd:hh:mm" (día = 24 h). Sin minutos: guion. */
    public static function ddhhmm($minutos): string
    {
        $m = (int) round((float) $minutos);
        if ($m <= 0) {
            return '—';
        }

        return sprintf('%02d:%02d:%02d', intdiv($m, 1440), intdiv($m % 1440, 60), $m % 60);
    }

    /** Últimos 24 meses para el selector ('2026-10' => 'Octubre 2026'). */
    public static function meses(): array
    {
        $out = [];
        $d   = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $out[$d->format('Y-m')] = ucfirst($d->locale('es')->translatedFormat('F Y'));
            $d = $d->copy()->subMonth();
        }

        return $out;
    }

    /** Rango [desde, hasta] a partir del filtro: fechas explícitas mandan sobre el mes. */
    public static function rango(?array $data): array
    {
        $data ??= [];
        if (! empty($data['desde']) || ! empty($data['hasta'])) {
            return [$data['desde'] ?: null, $data['hasta'] ?: null];
        }
        if (! empty($data['mes'])) {
            $ini = Carbon::createFromFormat('Y-m-d', $data['mes'] . '-01')->startOfMonth();

            return [$ini->toDateString(), $ini->copy()->endOfMonth()->toDateString()];
        }

        return [null, null];
    }
}
