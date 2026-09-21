<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentFile extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'banco',
        'archivo_path',
        'cantidad_registros',
        'monto_total',
        'generado_por',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
