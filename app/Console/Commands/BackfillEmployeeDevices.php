<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDevice;
use Illuminate\Console\Command;

class BackfillEmployeeDevices extends Command
{
    protected $signature = 'backfill:employee-devices';

    protected $description = 'Migra reloj_uid/reloj_id existentes de employees a la tabla employee_devices';

    public function handle()
    {
        $creados = 0;

        // Caso 1: empleados con reloj_uid lleno
        Employee::whereNotNull('reloj_uid')->each(function ($emp) use (&$creados) {
            $device = EmployeeDevice::firstOrCreate([
                'employee_id' => $emp->id,
                'location_id' => $emp->location_id,
                'reloj_uid'   => $emp->reloj_uid,
            ], [
                'reloj_id' => $emp->reloj_id,
                'active'   => true,
            ]);
            if ($device->wasRecentlyCreated) $creados++;
        });

        // Caso 2: empleados sin reloj_uid pero con reloj_id (usan reloj_id como fallback de UID)
        Employee::whereNull('reloj_uid')->whereNotNull('reloj_id')->each(function ($emp) use (&$creados) {
            $device = EmployeeDevice::firstOrCreate([
                'employee_id' => $emp->id,
                'location_id' => $emp->location_id,
                'reloj_uid'   => (string) $emp->reloj_id,
            ], [
                'reloj_id' => $emp->reloj_id,
                'active'   => true,
            ]);
            if ($device->wasRecentlyCreated) $creados++;
        });

        $this->info("Backfill completado: {$creados} vínculos nuevos creados. Total en tabla: " . EmployeeDevice::count());
    }
}
