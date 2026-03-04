<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeCredential extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'password',
        'active',
        'ultimo_acceso',
        'token_dispositivo',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'active'        => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];

    // ── Relación ──────────────────────────────────────────────────────────────

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Auth: el "username" para login es el DNI del empleado ─────────────────

    /**
     * Laravel busca el usuario por este campo al hacer Auth::attempt().
     * Lo sobreescribimos para que busque por DNI a través de la relación.
     * En realidad usaremos un método manual en el controlador, pero
     * este campo es requerido por el contrato de Authenticatable.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }
}
