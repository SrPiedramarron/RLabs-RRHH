<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'fecha',
        'nombre',
        'tipo',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    // Relaciones
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Helpers
    public function getTipoColorAttribute(): string
    {
        return match($this->tipo) {
            'nacional'  => 'danger',
            'regional'  => 'warning',
            'empresa'   => 'info',
            default     => 'gray',
        };
    }
}