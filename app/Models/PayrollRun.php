<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'company_id',
        'periodo_tipo',
        'quincena',
        'mes',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'fecha_pago',
        'moneda',
        'cuenta_cargo_pago',
        'referencia',
        'estado',
        'aprobada_en',
        'aprobada_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_pago' => 'date',
        'aprobada_en' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function paymentFiles(): HasMany
    {
        return $this->hasMany(PaymentFile::class);
    }
}
