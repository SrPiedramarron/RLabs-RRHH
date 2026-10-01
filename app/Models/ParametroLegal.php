<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametroLegal extends Model
{
    protected $table = 'parametros_legales';

    const RMV = 'rmv';
    const UIT = 'uit';
    const TASA_ONP = 'tasa_onp';

    protected $fillable = [
        'clave', 'descripcion', 'valor', 'vigente_desde', 'vigente_hasta',
    ];

    protected $casts = [
        'valor'         => 'float',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    /**
     * Devuelve el valor vigente de un parámetro legal (RMV, UIT, tasa ONP,
     * etc.) en una fecha dada — por defecto hoy. Mismo patrón que
     * AfpTasa::vigentePara(), para poder recalcular correctamente periodos
     * pasados con el valor que tenían en ese momento, aunque hoy ya haya
     * cambiado.
     */
    public static function valor(string $clave, $fecha = null): float
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : now()->toDateString();

        $parametro = static::where('clave', $clave)
            ->where('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if (!$parametro) {
            throw new \RuntimeException("No hay un valor configurado para el parámetro legal '{$clave}' vigente el {$fecha}. Configúralo en Administración > Parámetros Legales.");
        }

        return (float) $parametro->valor;
    }
}
