<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionUpload extends Model
{
    protected $fillable = [
        'periodo',
        'mes_nombre',
        'archivo_cobranzas',
        'archivo_comisiones',
        'estado',
        'error_mensaje',
        'total_facturas',
        'total_cobradas',
        'total_pendientes',
        'total_anuladas',
        'total_base_cobrada',
        'total_comision',
        'procesado_por',
        'vendedores_sin_match',
    ];

    protected $casts = [
        'total_base_cobrada' => 'decimal:2',
        'total_comision'     => 'decimal:2',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(ComisionDetalle::class);
    }

    public function detallesPorVendedor(string $vendedor): HasMany
    {
        return $this->hasMany(ComisionDetalle::class)->where('vendedor', $vendedor);
    }

    public function getResumenVendedoresAttribute(): array
    {
        return $this->detalles()
            ->selectRaw('vendedor, COUNT(*) as total, SUM(CASE WHEN estado="cobrada" THEN 1 ELSE 0 END) as cobradas, SUM(base_comision_cobrada) as base_cobrada, SUM(comision_calculada) as comision')
            ->groupBy('vendedor')
            ->orderBy('vendedor')
            ->get()
            ->toArray();
    }
}
