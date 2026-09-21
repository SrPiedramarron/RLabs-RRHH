<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'monto_neto',
    ];

    protected $casts = [
        'monto_neto' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollEntryLine::class);
    }

    /**
     * Recalcula monto_neto a partir de sus líneas (ingresos - descuentos)
     * y lo persiste. Llamar después de crear/editar líneas.
     */
    public function recalcularNeto(): void
    {
        $neto = $this->lines()
            ->with('payrollConcept')
            ->get()
            ->sum(fn (PayrollEntryLine $line) => $line->payrollConcept->esIngreso() ? $line->monto : -$line->monto);

        $this->update(['monto_neto' => $neto]);
    }
}
