<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'nombre',
        'hora_entrada',
        'hora_salida',
        'tolerancia_minutos',
        'refrigerio_inicio',
        'refrigerio_fin',
        'dias_laborables',
        'es_nocturno',
    ];

    protected $casts = [
        'dias_laborables' => 'array',
        'es_nocturno'     => 'boolean',
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

    // Helper: nombre de los días laborables
    public function getNombreDiasAttribute(): string
    {
        $nombres = [
            1 => 'Lun', 2 => 'Mar', 3 => 'Mié',
            4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
        ];

        return collect($this->dias_laborables)
            ->map(fn($d) => $nombres[$d] ?? $d)
            ->join(', ');
    }
}
