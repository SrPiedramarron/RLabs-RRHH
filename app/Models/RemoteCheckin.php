<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RemoteCheckin extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'tipo',
        'fecha_hora',
        'latitud',
        'longitud',
        'precision_metros',
        'foto_path',
        'estado_facial',
        'confianza_facial',
        'ip_address',
        'user_agent',
        'attendance_record_id',
        'estado_procesado',
        'notas_supervisor',
    ];

    protected $casts = [
        'fecha_hora'       => 'datetime',
        'confianza_facial' => 'decimal:4',
        'latitud'          => 'decimal:7',
        'longitud'         => 'decimal:7',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendanceRecord(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Porcentaje de confianza facial formateado (ej: "94.5%")
     */
    public function getConfianzaPorcentajeAttribute(): ?string
    {
        if ($this->confianza_facial === null) return null;
        return round((1 - $this->confianza_facial) * 100, 1) . '%';
        // face_recognition devuelve "distancia" (menor = más parecido)
        // lo invertimos para mostrar como porcentaje de similitud
    }

    /**
     * ¿El checkin fue aprobado por validación facial?
     */
    public function getFacialAprobadoAttribute(): bool
    {
        return $this->estado_facial === 'aprobado';
    }

    /**
     * URL pública de la foto del checkin
     */
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path ? asset('storage/' . $this->foto_path) : null;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->where('estado_procesado', 'pendiente');
    }

    public function scopeDeHoy($query)
    {
        return $query->whereDate('fecha_hora', today());
    }
}
