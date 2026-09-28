<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitud extends Model
{
    protected $table = 'solicitudes';

    protected $fillable = [
        'employee_id',
        'company_id',
        'tipo',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'fecha_registro',
        'hora_entrada_solicitada',
        'hora_salida_solicitada',
        'motivo_suspension_plame',
        'motivo',
        'adjunto_path',
        'revisado_por',
        'revisado_at',
        'comentario_revision',
    ];

    protected $casts = [
        'fecha_inicio'   => 'date',
        'fecha_fin'      => 'date',
        'fecha_registro' => 'date',
        'revisado_at'    => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'vacaciones'        => 'Vacaciones',
            'permiso'           => 'Permiso',
            'correccion_horas'  => 'Corrección de horas',
            default             => '—',
        };
    }

    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'warning',
            'aprobada'  => 'success',
            'rechazada' => 'danger',
            default     => 'gray',
        };
    }
}
