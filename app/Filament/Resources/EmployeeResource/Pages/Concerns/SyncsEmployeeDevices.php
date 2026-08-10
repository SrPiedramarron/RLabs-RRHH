<?php

namespace App\Filament\Resources\EmployeeResource\Pages\Concerns;

use App\Models\Employee;
use App\Models\EmployeeDevice;

trait SyncsEmployeeDevices
{
    protected function syncSedesAdicionales(Employee $employee, array $sedeIds): void
    {
        // Nunca duplicar la sede principal como "adicional"
        $sedeIds = array_diff($sedeIds, [$employee->location_id]);

        // Crear/reactivar los vínculos seleccionados
        foreach ($sedeIds as $locationId) {
            EmployeeDevice::updateOrCreate(
                ['employee_id' => $employee->id, 'location_id' => $locationId],
                ['reloj_uid' => (string) $employee->reloj_id, 'reloj_id' => $employee->reloj_id, 'active' => true]
            );
        }

        // Desactivar (no borrar, por historial) los que se quitaron
        EmployeeDevice::where('employee_id', $employee->id)
            ->where('location_id', '!=', $employee->location_id)
            ->whereNotIn('location_id', $sedeIds)
            ->update(['active' => false]);
    }
}
