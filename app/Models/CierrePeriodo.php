<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cierre de un mes de planilla por empresa. Con el mes cerrado, el sistema
 * no deja recalcular planilla, quincena, honorarios, gratificación, CTS ni
 * utilidades de ese mes, para que lo ya pagado quede tal cual aunque cambien
 * fichas de trabajadores o parámetros.
 */
class CierrePeriodo extends Model
{
    protected $table = 'cierres_periodo';

    protected $fillable = [
        'company_id', 'periodo', 'cerrado', 'cerrado_por', 'cerrado_at',
        'reabierto_por', 'reabierto_at', 'motivo_reapertura',
    ];

    protected $casts = [
        'cerrado'      => 'boolean',
        'cerrado_at'   => 'datetime',
        'reabierto_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function cerradoPor(): BelongsTo { return $this->belongsTo(User::class, 'cerrado_por'); }
    public function reabiertoPor(): BelongsTo { return $this->belongsTo(User::class, 'reabierto_por'); }

    public static function estaCerrado(int $companyId, string $periodo): bool
    {
        return static::where('company_id', $companyId)->where('periodo', $periodo)->where('cerrado', true)->exists();
    }

    /** Lanza si el mes está cerrado. $que describe lo que se intentaba calcular. */
    public static function asegurarAbierto(int $companyId, string $periodo, string $que = 'la planilla'): void
    {
        if (static::estaCerrado($companyId, $periodo)) {
            throw new \RuntimeException("El periodo {$periodo} está cerrado: no se puede recalcular {$que}. Pide reabrirlo en Planilla → Cierre de periodos.");
        }
    }

    /** Mes de pago de gratificación/CTS (para cerrarlos junto con el mes). */
    public static function mesDe(string $tipo, int $anio): string
    {
        $mes = ['julio' => 7, 'diciembre' => 12, 'mayo' => 5, 'noviembre' => 11][$tipo] ?? 1;

        return sprintf('%04d-%02d', $anio, $mes);
    }
}
