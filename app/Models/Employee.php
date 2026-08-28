<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'location_id',
        'department_id',
        'schedule_id',
        'nombres',
        'apellidos',
        'dni',
        'codigo_empleado',
        'cargo',
        'fecha_ingreso',
        'fecha_cese',
        'exonerado_registro',
        'motivo_exoneracion',
        'reloj_uid',
        'reloj_id',
        'sueldo_base',
        'sistema_pensiones',
        'aplica_comision_flujo_afp',
        'movilidad_mensual_maxima',
        'aplica_5ta_categoria',
        'aplica_comision',
        'aplica_asignacion_familiar',
        'movilidad_diaria',
        'monto_eps_mensual_con_igv',
        'bono_encargatura',
        'active',
        'foto_perfil',          // ← nuevo
    ];

    protected $casts = [
        'fecha_ingreso'      => 'date',
        'fecha_cese'         => 'date',
        'exonerado_registro' => 'boolean',
        'active'             => 'boolean',
    ];

    // ── Relaciones existentes ─────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'employee_schedules');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // ── Relaciones nuevas ─────────────────────────────────────────────────────

    public function credential(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeCredential::class);
    }

    public function remoteCheckins(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RemoteCheckin::class);
    }


    // ── Mutators: forzar mayúsculas ───────────────────────────────────────────
    public function setNombresAttribute(string $value): void
    {
        $this->attributes['nombres'] = mb_strtoupper(trim($value));
    }

    public function setApellidosAttribute(string $value): void
    {
        $this->attributes['apellidos'] = mb_strtoupper(trim($value));
    }
    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return $this->apellidos . ', ' . $this->nombres;
    }

    public function getNombreCompletoNormalAttribute(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }

    /**
     * URL pública de la foto de perfil
     */
    public function getFotoPerfilUrlAttribute(): ?string
    {
        return $this->foto_perfil ? asset('storage/' . $this->foto_perfil) : null;
    }

    /**
     * ¿Tiene credenciales activas para la PWA?
     */
    public function getTieneAccesoRemotoAttribute(): bool
    {
        return $this->credential !== null && $this->credential->active;
    }

public function devices()
{
    return $this->hasMany(EmployeeDevice::class);
}
}
