<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocumento extends Model
{
    protected $table = 'employee_documentos';

    protected $fillable = [
        'employee_id',
        'tipo',
        'nombre',
        'archivo',
        'fecha_documento',
        'observacion',
    ];

    protected $casts = [
        'fecha_documento' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getArchivoUrlAttribute(): ?string
    {
        return $this->archivo ? asset('storage/' . $this->archivo) : null;
    }
}
