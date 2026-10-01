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
        if (($data['modo'] ?? 'excel') === 'manual') {
            return $this->crearAjusteManual($data);
        }

        // Deducir el nombre legible del período (e.g. "2026-03" → "MARZO 2026")
        $data['mes_nombre']    = $this->periodoANombre($data['periodo']);
        $data['procesado_por'] = Auth::id();
        $data['estado']        = 'procesando';
        unset($data['modo'], $data['employee_id'], $data['monto_manual']);

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

    /**
     * "Ajuste manual" (sin Excel): crea una ComisionUpload + un único
     * ComisionDetalle "cobrada" por el monto ingresado, para un trabajador
     * puntual. Se suma a la planilla exactamente igual que una comisión
     * real (PlanillaService lee de comision_detalles sin distinguir el
     * origen) — pedido oct. 2026, para bonos/ajustes sin Excel de por
     * medio.
     */
    private function crearAjusteManual(array $data): Model
    {
        $empleado = \App\Models\Employee::findOrFail($data['employee_id']);
        $monto    = round((float) $data['monto_manual'], 2);

        $record = static::getModel()::create([
            'company_id'          => $data['company_id'],
            'periodo'             => $data['periodo'],
            'mes_nombre'          => $this->periodoANombre($data['periodo']),
            'archivo_cobranzas'   => 'MANUAL',
            'archivo_comisiones'  => 'MANUAL',
            'estado'              => 'completado',
            'total_facturas'      => 1,
            'total_cobradas'      => 1,
            'total_base_cobrada'  => $monto,
            'total_comision'      => $monto,
            'procesado_por'       => Auth::id(),
        ]);

        \App\Models\ComisionDetalle::create([
            'comision_upload_id'     => $record->id,
            'employee_id'            => $empleado->id,
            'periodo'                => $data['periodo'],
            'vendedor'               => $empleado->nombre_completo,
            'numdoc'                 => 'MANUAL',
            'tipo_doc'               => 'MA',
            'base_comision_cobrada'  => $monto,
            'estado'                 => 'cobrada',
            'comision_calculada'     => $monto,
            'porcentaje_comision'    => 0,
        ]);

        Notification::make()
            ->title('Ajuste manual registrado')
            ->body("{$empleado->nombre_completo}: S/ " . number_format($monto, 2) . " para {$record->mes_nombre}.")
            ->success()
            ->send();

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
