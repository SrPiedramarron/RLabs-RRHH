<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractRenewal extends Model
{
    protected $fillable = [
        'employee_id', 'numero_renovacion', 'fecha_fin_anterior', 'fecha_fin_nueva',
        'observacion', 'renovado_por', 'renovado_at',
    ];

    protected $casts = [
        'fecha_fin_anterior' => 'date',
        'fecha_fin_nueva'    => 'date',
        'renovado_at'        => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function renovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renovado_por');
    }
}
