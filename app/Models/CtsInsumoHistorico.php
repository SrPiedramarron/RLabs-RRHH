<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtsInsumoHistorico extends Model
{
    protected $table = 'cts_insumos_historicos';

    protected $fillable = ['employee_id', 'periodo', 'concepto', 'monto', 'nota'];
}
