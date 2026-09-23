<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gratificacion extends Model
{
    protected $table = 'gratificaciones';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'tipo', 'anio',
        'nombres', 'apellidos', 'dni', 'cargo',
        'sueldo_base', 'asignacion_familiar',
        'meses_computables',
        'meses_con_comisiones', 'promedio_comisiones',
        'meses_con_horas_extra', 'promedio_horas_extra',
        'remuneracion_computable', 'monto_gratificacion',
        'bonificacion_extraordinaria', 'monto_total',
        'calculado_por', 'calculado_at',
    ];

    protected $casts = [
        'calculado_at' => 'datetime',
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

    public function getMesNombreAttribute(): string
    {
        return $this->tipo === 'julio'
            ? "Gratificación Julio {$this->anio}"
            : "Gratificación Diciembre {$this->anio}";
    }
}
