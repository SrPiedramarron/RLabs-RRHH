<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoHistorico5ta extends Model
{
    protected $table = 'ingresos_historicos_5ta';

    protected $fillable = [
        'employee_id', 'company_id', 'periodo', 'concepto', 'monto', 'fuente',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
