<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaLiquidacion extends Model
{
    protected $table = 'planilla_liquidaciones'; // ? agregar esta línea

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'mes_nombre',
        'nombres', 'apellidos', 'dni', 'cargo', 'sueldo_base',
        'sistema_pensiones', 'aplica_5ta_categoria',
        'dias_laborables', 'dias_trabajados', 'dias_falta',
        'dias_justificados', 'total_minutos_tarde',
	'dias_vacaciones', 'vacaciones',
        'horas_extra_diurnas', 'horas_extra_nocturnas',
        'sueldo_proporcional',
        'importe_horas_extra_diurnas', 'importe_horas_extra_nocturnas',
        'comisiones', 'asignacion_familiar', 'bono_movilidad', 'bono_encargatura', 'bonos_especiales', 'otros_descuentos', 'adelanto', 'adelanto_quincena', 'subsidio_enfermedad', 'subsidio_maternidad',
        'descuento_tardanzas', 'descuento_faltas',
        'remuneracion_bruta',
        'porcentaje_pension', 'descuento_pension',
        'afp_comision_flujo', 'afp_prima_seguro', 'afp_aporte_obligatorio',
        'descuento_5ta_categoria',
        'total_descuentos', 'neto_pagar',
        'essalud_empleador', 'eps_credito', 'eps_aporte_empresa', 'eps_descuento_trabajador', 'seguro_vida_empleador',
        'calculado_por', 'calculado_at',
        'boleta_firmada_path', 'boleta_firmada_at', 'boleta_firmada_por',
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

    public function getTieneBoletaFirmadaAttribute(): bool
    {
        return !empty($this->boleta_firmada_path);
    }
}
