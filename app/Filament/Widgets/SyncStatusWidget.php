<?php

namespace App\Filament\Widgets;

use App\Models\Location;
use App\Services\ZKTecoService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SyncStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.sync-status-widget';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function getLocations()
    {
        return Location::with('company')->where('active', true)->where('company_id', session('active_company_id'))->get();
    }

    public function sincronizar(int $locationId): void
    {
        $location  = Location::findOrFail($locationId);
        $zkService = app(ZKTecoService::class);
        $log       = $zkService->syncLocation($location);

        if ($log->estado === 'completado') {
            Notification::make()
                ->title("✅ {$location->nombre}: {$log->registros_nuevos} registros nuevos")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("❌ Error en {$location->nombre}")
                ->body($log->error_mensaje)
                ->danger()
                ->send();
        }
    }
}