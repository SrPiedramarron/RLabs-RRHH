<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\Concerns\SyncsEmployeeDevices;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/* class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $existente = Employee::where('dni', $data['dni'])
            ->where('company_id', $data['company_id'])
            ->first();

        if ($existente) {
            // No crear un empleado nuevo: solo vincular (o actualizar el vínculo) a la sede indicada
            $device = EmployeeDevice::updateOrCreate(
                [
                    'employee_id' => $existente->id,
                    'location_id' => $data['location_id'],
                ],
                [
                    'reloj_uid' => $data['reloj_uid'],
                    'reloj_id'  => $data['reloj_id'] ?? null,
                    'active'    => true,
                ]
            );

            Notification::make()
                ->title('Empleado ya existente')
                ->body("{$existente->nombre_completo} ya estaba registrado(a). Se " . ($device->wasRecentlyCreated ? 'vinculó' : 'actualizó el vínculo de') . " esta sede en lugar de crear un empleado duplicado.")
                ->warning()
                ->persistent()
                ->send();

            $this->halt();

            return $existente;
        }

        return static::getModel()::create($data);
    }
}
*/
class CreateEmployee extends CreateRecord
{
    use SyncsEmployeeDevices;

    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $sedesAdicionales = $data['sedes_adicionales'] ?? [];
        unset($data['sedes_adicionales']); // no es columna de employees

        $existente = Employee::where('dni', $data['dni'])
            ->where('company_id', $data['company_id'])
            ->first();

        if ($existente) {
            $device = EmployeeDevice::updateOrCreate(
                ['employee_id' => $existente->id, 'location_id' => $data['location_id']],
                ['reloj_uid' => $data['reloj_uid'], 'reloj_id' => $data['reloj_id'] ?? null, 'active' => true]
            );

            $this->syncSedesAdicionales($existente, $sedesAdicionales);

            Notification::make()
                ->title('Empleado ya existente')
                ->body("{$existente->nombre_completo} ya estaba registrado(a). Se " . ($device->wasRecentlyCreated ? 'vinculó' : 'actualizó el vínculo de') . " esta sede en lugar de crear un empleado duplicado.")
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
            return $existente;
        }

        $record = static::getModel()::create($data);
        $this->syncSedesAdicionales($record, $sedesAdicionales);
        return $record;
    }
}
