<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HonorarioPago extends Model
{
    protected $table = 'honorarios_pagos';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'quincena',
        'honorario_bruto', 'retencion_4ta', 'neto_pagar', 'suspension_4ta',
        'recibo_numero', 'recibo_fecha', 'pagado', 'pagado_at',
    ];

    protected $casts = [
        'recibo_fecha'   => 'date',
        'pagado'         => 'boolean',
        'pagado_at'      => 'datetime',
        'suspension_4ta' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
