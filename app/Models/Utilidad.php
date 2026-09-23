<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utilidad extends Model
{
    protected $table = 'utilidades';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'anio_ejercicio',
        'nombres', 'apellidos', 'dni', 'cargo', 'sueldo_base',
        'dias_trabajados_anual', 'remuneracion_anual',
        'monto_por_dias', 'monto_por_remuneracion', 'monto_bruto',
        'tope_18_remuneraciones', 'tope_aplicado', 'monto_pagado',
        'calculado_por', 'calculado_at',
    ];

    protected $casts = [
        'tope_aplicado' => 'boolean',
        'calculado_at'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return $this->apellidos . ', ' . $this->nombres;
    }
}
