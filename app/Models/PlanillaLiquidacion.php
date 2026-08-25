<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaLiquidacion extends Model
{
    protected $table = 'planilla_liquidaciones'; // ? agregar esta l�nea

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'mes_nombre',
        'nombres', 'apellidos', 'dni', 'cargo', 'sueldo_base',
        'sistema_pensiones', 'aplica_5ta_categoria',
        'dias_laborables', 'dias_trabajados', 'dias_falta',
        'dias_justificados', 'total_minutos_tarde',
        'horas_extra_diurnas', 'horas_extra_nocturnas',
        'sueldo_proporcional',
        'importe_horas_extra_diurnas', 'importe_horas_extra_nocturnas',
        'comisiones', 'asignacion_familiar', 'bono_movilidad', 'bonos_especiales',
        'descuento_tardanzas', 'descuento_faltas',
        'remuneracion_bruta',
        'porcentaje_pension', 'descuento_pension',
        'afp_comision_flujo', 'afp_prima_seguro', 'afp_aporte_obligatorio',
        'descuento_5ta_categoria',
        'total_descuentos', 'neto_pagar',
        'essalud_empleador', 'seguro_vida_empleador',
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

    public function getSistemaPensionesLabelAttribute(): string
    {
        return match($this->sistema_pensiones) {
            'onp'           => 'ONP',
            'afp_prima'     => 'AFP Prima',
            'afp_integra'   => 'AFP Integra',
            'afp_habitat'   => 'AFP Habitat',
            'afp_profuturo' => 'AFP Profuturo',
            default         => $this->sistema_pensiones,
        };
    }
}
