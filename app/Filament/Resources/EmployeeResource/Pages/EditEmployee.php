<?php
namespace App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\Concerns\SyncsEmployeeDevices;
use App\Models\EmployeeCredential;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $sedesAdicionales = $data['sedes_adicionales'] ?? [];
        unset($data['sedes_adicionales']);

        $passwordNueva    = $data['credential']['password_nueva'] ?? null;
        $credentialActive = $data['credential']['active'] ?? false;
        $resetearDni      = $data['credential']['resetear_dni'] ?? false;
        unset($data['credential']);

        $record->fill($data)->save();

        $this->syncSedesAdicionales($record, $sedesAdicionales);

        if ($passwordNueva || $resetearDni || $credentialActive || $record->credential) {
            $credData = ['active' => $credentialActive];
            if ($passwordNueva) {
                $credData['password'] = Hash::make($passwordNueva);
            } elseif ($resetearDni || !$record->credential) {
                $credData['password'] = Hash::make($record->dni);
            }
            EmployeeCredential::updateOrCreate(
                ['employee_id' => $record->id],
                $credData
            );
        }

        return $record;
    }
}
