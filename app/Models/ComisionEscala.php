<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionEscala extends Model
{
    protected $fillable = [
        'employee_id',
        'monto_desde',
        'porcentaje',
    ];

    protected $casts = [
        'monto_desde' => 'decimal:2',
        'porcentaje'  => 'decimal:4',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
