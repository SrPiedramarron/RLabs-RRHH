<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacacionHistorial extends Model
{
    protected $table = 'vacacion_historiales';

    protected $fillable = [
        'employee_id',
        'fecha_inicio',
        'fecha_fin',
        'dias',
        'observacion',
        'registrado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
