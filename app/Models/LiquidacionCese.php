<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionCese extends Model
{
    protected $table = 'liquidaciones_cese';

    protected $fillable = [
        'employee_id', 'company_id', 'fecha_cese', 'motivo_cese',
        'nombres', 'apellidos', 'dni', 'cargo',
        'sueldo_base', 'asignacion_familiar',
        'dias_vacaciones_truncas', 'monto_vacaciones_truncas',
        'remuneracion_vacacional_pendiente', 'indemnizacion_vacacional',
        'meses_gratificacion_trunca', 'monto_gratificacion_trunca', 'bonificacion_extraordinaria_trunca',
        'meses_cts_trunca', 'monto_cts_trunca',
        'indemnizacion', 'monto_total',
        'calculado_por', 'calculado_at',
    ];

    protected $casts = [
        'fecha_cese'   => 'date',
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

    public function getMotivoLabelAttribute(): string
    {
        return match ($this->motivo_cese) {
            'renuncia' => 'Renuncia voluntaria',
            'despido_justificado' => 'Despido justificado',
            'despido_arbitrario' => 'Despido arbitrario',
            'mutuo_disenso' => 'Mutuo disenso',
            'terminacion_contrato' => 'Término de contrato',
            default => $this->motivo_cese,
        };
    }
}
