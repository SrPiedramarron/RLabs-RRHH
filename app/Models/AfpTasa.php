<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AfpTasa extends Model
{
    protected $fillable = [
        'afp', 'comision_flujo', 'prima_seguro', 'aporte_obligatorio',
        'tope_remuneracion_asegurable', 'vigente_desde', 'vigente_hasta',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    protected static function booted(): void
    {
        // Al guardar una tasa nueva, la anterior de la misma AFP que seguía abierta
        // se cierra el día previo, para que nunca haya dos tasas vigentes a la vez.
        static::saved(function (self $tasa) {
            static::where('afp', $tasa->afp)
                ->where('id', '!=', $tasa->id)
                ->where('vigente_desde', '<', $tasa->vigente_desde)
                ->where(function ($q) use ($tasa) {
                    $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $tasa->vigente_desde);
                })
                ->update(['vigente_hasta' => $tasa->vigente_desde->copy()->subDay()->toDateString()]);
        });
    }

    /**
     * Devuelve la tasa vigente para una AFP en una fecha dada (por defecto hoy).
     */
    public static function vigentePara(string $afp, ?string $fecha = null): ?self
    {
        $fecha = $fecha ?? now()->toDateString();

        return static::where('afp', $afp)
            ->where('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->first();
    }
}
