<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionDetalle extends Model
{
    protected $fillable = [
        'comision_upload_id',
        'periodo',
        'vendedor',
        'numdoc',
        'tipo_doc',
        'cod_cliente',
        'razon_social',
        'condicion',
        'fecha_emision',
        'fecha_vencimiento',
        'moneda',
        'tipo_cambio',
        'base_comision_venta',
        'v_venta_contado',
        'v_venta_credito',
        'base_comision_cobrada',
        'fecha_pago',
        'forma_pago',
        'importe_cobrado',
        'estado',
        'mes_cobro',
        'comision_calculada',
        'porcentaje_comision',
    ];

    protected $casts = [
        'fecha_emision'     => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(ComisionUpload::class, 'comision_upload_id');
    }

    public function getEstadoBadgeColorAttribute(): string
    {
        return match($this->estado) {
            'cobrada'   => 'success',
            'pendiente' => 'warning',
            'anulada'   => 'danger',
            default     => 'gray',
        };
    }
}
