<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'reloj_uid',
        'reloj_id',
        'timestamp',
        'tipo',
        'estado',
        'raw_data',
        'procesado',
        'created_at',
    ];

    protected $casts = [
        'timestamp'  => 'datetime',
        'created_at' => 'datetime',
        'raw_data'   => 'array',
        'procesado'  => 'boolean',
    ];

    // Relaciones
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // Helpers
    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            0 => 'Entrada',
            1 => 'Salida',
            default => 'Desconocido',
        };
    }
}