<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'location_id',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'minutos_tarde',
        'minutos_trabajados',
        'horas_ordinarias',
        'horas_extra_diurnas',
        'horas_extra_nocturnas',
        'estado',
        'justificado',
        'observacion',
        'corregido_manualmente',
    ];

    protected $casts = [
        'fecha'                 => 'date',
        'hora_entrada'          => 'datetime',
        'hora_salida'           => 'datetime',
        'horas_ordinarias'      => 'decimal:2',
        'horas_extra_diurnas'   => 'decimal:2',
        'horas_extra_nocturnas' => 'decimal:2',
        'justificado'           => 'boolean',
        'corregido_manualmente' => 'boolean',
    ];

    // Relaciones
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // Helpers
    public function getEstadoColorAttribute(): string
    {
        return match($this->estado) {
            'presente'   => 'success',
            'tarde'      => 'warning',
            'ausente'    => 'danger',
            'feriado'    => 'info',
            'descanso'   => 'gray',
            'permiso'    => 'info',
            'vacaciones' => 'info',
            default      => 'gray',
        };
    }

    public function getTiempoTardeAttribute(): string
    {
        if ($this->minutos_tarde === 0) return '—';
        $h = intdiv($this->minutos_tarde, 60);
        $m = $this->minutos_tarde % 60;
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    public function getTotalHorasExtraAttribute(): float
    {
        return round($this->horas_extra_diurnas + $this->horas_extra_nocturnas, 2);
    }
}