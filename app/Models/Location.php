<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'nombre',
        'direccion',
        'ubigeo',
        'reloj_ip',
        'reloj_puerto',
        'reloj_modelo',
        'reloj_activo',
        'ultima_sync',
        'sync_estado',
        'sync_error_msg',
        'active',
    ];

    protected $casts = [
        'reloj_activo' => 'boolean',
        'active'       => 'boolean',
        'ultima_sync'  => 'datetime',
    ];

    // Relaciones
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }
}