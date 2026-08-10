<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDevice extends Model
{
    protected $fillable = ['employee_id', 'location_id', 'reloj_uid', 'reloj_id', 'active'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
