<?php

namespace App\Filament\Resources\PayrollRunResource\Actions;

use App\Models\PayrollRun;
use App\Services\PaymentFile\BcpHaberesExporter;
use App\Services\PaymentFile\PayrollRunToBcpBatchMapper;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Registrar como acción de tabla o de página en PayrollRunResource:
 *
 *   use App\Filament\Resources\PayrollRunResource\Actions\GenerateBcpFileAction;
 *   ...
 *   ->actions([ GenerateBcpFileAction::make(), ... ])
 */
class GenerateBcpFileAction
{
    public static function make(): Action
    {
        return Action::make('generar_archivo_bcp')
            ->label('Generar archivo BCP')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (PayrollRun $record) => $record->estado === 'aprobada')
            ->requiresConfirmation()
            ->modalDescription('Se generará el archivo de carga masiva para BCP con los trabajadores que tengan cuenta propia BCP registrada. Las transferencias interbancarias por BCP (CCI) todavía no están soportadas.')
            ->action(function (PayrollRun $record) {
                try {
                    [$batch, $warnings] = (new PayrollRunToBcpBatchMapper())->map($record);

                    if (empty($batch->beneficiaries)) {
                        Notification::make()
                            ->title('No hay beneficiarios válidos para generar el archivo')
                            ->body(implode("\n", $warnings))
                            ->danger()
                            ->send();

                        return;
                    }

                    $contents = (new BcpHaberesExporter())->export($batch);

                    $fileName = sprintf(
                        'pagos/bcp/%s_%s_Q%s_%s.txt',
                        $record->company->ruc,
                        $record->anio,
                        $record->quincena ?? $record->mes,
                        now()->format('YmdHis')
                    );

                    Storage::disk('local')->put($fileName, $contents);

                    $record->paymentFiles()->create([
                        'banco' => 'BCP',
                        'archivo_path' => $fileName,
                        'cantidad_registros' => count($batch->beneficiaries),
                        'monto_total' => $batch->montoTotal(),
                        'generado_por' => Auth::id(),
                    ]);

                    $title = 'Archivo BCP generado: ' . count($batch->beneficiaries) . ' registros por S/ ' . number_format($batch->montoTotal(), 2);

                    if (! empty($warnings)) {
                        Notification::make()
                            ->title($title)
                            ->body("Trabajadores excluidos:\n" . implode("\n", $warnings))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()->title($title)->success()->send();
                    }
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('No se pudo generar el archivo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
