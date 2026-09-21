<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollConcept extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'afecto_onp_afp',
        'afecto_renta_5ta',
        'active',
    ];

    protected $casts = [
        'afecto_onp_afp' => 'boolean',
        'afecto_renta_5ta' => 'boolean',
        'active' => 'boolean',
    ];

    public function esIngreso(): bool
    {
        return $this->tipo === 'ingreso';
    }

    public function esDescuento(): bool
    {
        return $this->tipo === 'descuento';
    }
}
