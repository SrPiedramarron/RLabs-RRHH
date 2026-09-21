<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntryLine extends Model
{
    protected $fillable = [
        'payroll_entry_id',
        'payroll_concept_id',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    public function payrollConcept(): BelongsTo
    {
        return $this->belongsTo(PayrollConcept::class);
    }
}
