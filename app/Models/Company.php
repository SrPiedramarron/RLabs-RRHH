<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'razon_social',
        'ruc',
        'direccion',
        'ubigeo',
        'telefono',
        'email',
        'logo_path',
        'active',
        'pago_quincenal',
    ];

    protected $casts = [
        'active'         => 'boolean',
        'pago_quincenal' => 'boolean',
    ];

    // Relaciones
    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function holidays()
    {
        return $this->hasMany(Holiday::class);
    }
}