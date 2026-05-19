<?php
namespace App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource;
use App\Jobs\SyncAttendanceJob;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sincronizar_todas')
                ->label('Sincronizar Todas las Sedes')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar Relojes')
                ->modalDescription('Se intentará conectar con todos los relojes activos. ¿Continuar?')
                ->action(function () {
                    SyncAttendanceJob::dispatch();
                    Notification::make()
                        ->title('Sincronización iniciada')
                        ->body('El proceso corre en background. Los registros estarán disponibles en unos momentos.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
