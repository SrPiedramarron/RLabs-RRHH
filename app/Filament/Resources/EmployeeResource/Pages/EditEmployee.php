<?php
namespace App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\Concerns\SyncsEmployeeDevices;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    use SyncsEmployeeDevices;

    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * El acceso remoto (contraseña / activo / resetear al DNI) ya NO se
     * maneja aquí — son botones de acción independientes en el propio
     * formulario (ver EmployeeResource::form(), sección "Acceso Remoto"),
     * porque depender del guardado general perdía el acceso en cada edición
     * (bug reportado por RRHH, set. 2026): el toggle "Acceso activo" se
     * enviaba en false aunque se viera activado, y el campo de contraseña
     * nueva tenía dehydrated(false) por lo que nunca llegaba a guardarse.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $sedesAdicionales = $data['sedes_adicionales'] ?? [];
        unset($data['sedes_adicionales']);

        $record->fill($data)->save();

        $this->syncSedesAdicionales($record, $sedesAdicionales);

        return $record;
    }
}
