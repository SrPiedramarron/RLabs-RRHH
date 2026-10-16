<?php

namespace App\Models;

use App\Support\Recordatorios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recordatorio extends Model
{
    protected $table = 'recordatorios';

    protected $fillable = [
        'company_id', 'tipo', 'activo', 'destinatarios', 'modo',
        'dia_mes', 'dias_antes', 'ventana_dias', 'hora', 'ultimo_envio_at',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'ultimo_envio_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Lista de correos ya separada y validada. */
    public function correos(): array
    {
        $partes = preg_split('/[;,\s]+/', (string) $this->destinatarios, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($partes, fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))));
    }

    /** "El día 1 de cada mes a las 08:00", "1 día antes, a las 08:00"… */
    public function getCuandoAttribute(): string
    {
        $hora = $this->hora ?: '08:00';

        return match ($this->modo) {
            'dia_mes'    => ($this->dia_mes >= 31 ? 'El último día de cada mes' : "El día {$this->dia_mes} de cada mes") . " a las {$hora}",
            'dias_antes' => ($this->dias_antes == 0 ? 'El mismo día' : "{$this->dias_antes} día(s) antes") . " del evento, a las {$hora}",
            default      => "Todos los días a las {$hora}",
        };
    }

    public function getTipoLabelAttribute(): string
    {
        return Recordatorios::TIPOS[$this->tipo]['label'] ?? $this->tipo;
    }
}
