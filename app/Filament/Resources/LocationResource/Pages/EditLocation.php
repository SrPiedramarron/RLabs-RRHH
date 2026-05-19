<?php
namespace App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource;
use App\Jobs\SyncAttendanceJob;
use App\Services\ZKTecoService;
use App\Services\ZKTecoSDKService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('sincronizar')
                ->label('Sincronizar Ahora')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar Reloj')
                ->modalDescription('Se intentará conectar con el reloj de esta sede. ¿Continuar?')
                ->action(function () {
                    $location = $this->record;

                    if ($location->reloj_tipo === 'zkadms') {
                        Notification::make()
                            ->title('Reloj ADMS')
                            ->body('Este reloj envía los registros automáticamente. No requiere sincronización manual.')
                            ->info()
                            ->send();
                        return;
                    }

                    // Para zkbio y zksdk despachar en background
                    SyncAttendanceJob::dispatch();

                    Notification::make()
                        ->title('Sincronización iniciada')
                        ->body('El proceso corre en background. Los registros estarán disponibles en unos momentos.')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'ultima_sync',
                        'sync_estado',
                        'sync_error_msg',
                    ]);
                }),
        ];
    }
}
