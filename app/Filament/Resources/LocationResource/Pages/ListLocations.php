<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Services\ZKTecoService;
use App\Models\Location;
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
                    $zkService = app(ZKTecoService::class);
                    $locations = Location::where('reloj_activo', true)->get();
                    $errores = 0;

                    foreach ($locations as $location) {
                        $log = $zkService->syncLocation($location);
                        if ($log->estado === 'error') $errores++;
                    }

                    if ($errores === 0) {
                        Notification::make()
                            ->title('Sincronización completada')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title("{$errores} sede(s) con error de conexión")
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}