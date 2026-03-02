<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SyncLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'iniciado_en',
        'finalizado_en',
        'estado',
        'registros_leidos',
        'registros_nuevos',
        'error_mensaje',
        'created_at',
    ];

    protected $casts = [
        'iniciado_en'   => 'datetime',
        'finalizado_en' => 'datetime',
        'created_at'    => 'datetime',
    ];

    // Relaciones
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // Helpers
    public function getDuracionAttribute(): string
    {
        if (!$this->finalizado_en) return 'En proceso...';
        $segundos = $this->iniciado_en->diffInSeconds($this->finalizado_en);
        return $segundos < 60 ? "{$segundos}s" : intdiv($segundos, 60) . 'm ' . ($segundos % 60) . 's';
    }

    public function getEstadoColorAttribute(): string
    {
        return match($this->estado) {
            'completado' => 'success',
            'error'      => 'danger',
            'ejecutando' => 'warning',
            default      => 'gray',
        };
    }
}