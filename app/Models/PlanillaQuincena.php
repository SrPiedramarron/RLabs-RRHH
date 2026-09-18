<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaQuincena extends Model
{
    protected $table = 'planilla_quincenas';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'mes_nombre',
        'nombres', 'apellidos', 'dni', 'cargo',
        'sueldo_base', 'asignacion_familiar', 'base_quincenal',
        'sistema_pensiones', 'porcentaje_pension', 'descuento_pension',
        'afp_comision_flujo', 'afp_prima_seguro', 'afp_aporte_obligatorio',
        'aplica_5ta_categoria', 'descuento_5ta_categoria',
        'neto_pagar',
        'calculado_por', 'calculado_at',
    ];

    protected $casts = [
        'aplica_5ta_categoria' => 'boolean',
        'calculado_at'         => 'datetime',
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
