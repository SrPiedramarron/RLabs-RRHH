<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtsDeposito extends Model
{
    protected $table = 'cts_depositos';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'tipo', 'anio',
        'nombres', 'apellidos', 'dni', 'cargo',
        'sueldo_base', 'asignacion_familiar',
        'meses_computables',
        'meses_con_comisiones', 'promedio_comisiones',
        'meses_con_horas_extra', 'promedio_horas_extra',
        'gratificacion_id', 'sexto_gratificacion',
        'remuneracion_computable', 'monto_cts',
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

    public function gratificacion(): BelongsTo
    {
        return $this->belongsTo(Gratificacion::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return $this->apellidos . ', ' . $this->nombres;
    }

    public function getMesNombreAttribute(): string
    {
        return $this->tipo === 'mayo'
            ? "CTS Mayo {$this->anio}"
            : "CTS Noviembre {$this->anio}";
    }
}
