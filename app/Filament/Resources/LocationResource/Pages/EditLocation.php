<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Services\ZKTecoService;
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
                    $zkService = app(ZKTecoService::class);
                    $log = $zkService->syncLocation($this->record);

                    if ($log->estado === 'completado') {
                        Notification::make()
                            ->title("Sincronización exitosa: {$log->registros_nuevos} registros nuevos")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Error de conexión')
                            ->body($log->error_mensaje)
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData([
                        'ultima_sync',
                        'sync_estado',
                        'sync_error_msg',
                    ]);
                }),
        ];
    }
}