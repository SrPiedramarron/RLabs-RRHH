<?php

namespace App\Filament\Resources\ComisionUploadResource\Pages;

use App\Filament\Resources\ComisionUploadResource;
use App\Services\ComisionesService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateComisionUpload extends CreateRecord
{
    protected static string $resource = ComisionUploadResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Deducir el nombre legible del período (e.g. "2026-03" → "MARZO 2026")
        $data['mes_nombre']    = $this->periodoANombre($data['periodo']);
        $data['procesado_por'] = Auth::id();
        $data['estado']        = 'procesando';

        $record = static::getModel()::create($data);

        // Procesar en el mismo request (para volúmenes pequeños como los de InProcess está bien;
        // si crece, mover a un Job con dispatch())
        try {
            app(ComisionesService::class)->procesar(
                $record,
                Storage::disk('public')->path($data['archivo_cobranzas']),
		Storage::disk('public')->path($data['archivo_comisiones']),
            );

            Notification::make()
                ->title('Comisiones procesadas correctamente')
                ->body("Período: {$record->mes_nombre} — {$record->total_facturas} facturas cruzadas.")
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al procesar los archivos')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        return $record;
    }

    private function periodoANombre(string $periodo): string
    {
        $meses = [
            '01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO',
            '04' => 'ABRIL', '05' => 'MAYO',    '06' => 'JUNIO',
            '07' => 'JULIO', '08' => 'AGOSTO',  '09' => 'SEPTIEMBRE',
            '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE',
        ];

        [$year, $month] = explode('-', $periodo);
        return ($meses[$month] ?? $month) . ' ' . $year;
    }
}
