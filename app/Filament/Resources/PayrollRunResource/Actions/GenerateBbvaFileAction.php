<?php

namespace App\Filament\Resources\PayrollRunResource\Actions;

use App\Models\PayrollRun;
use App\Services\PaymentFile\BbvaHaberesExporter;
use App\Services\PaymentFile\PayrollRunToBbvaBatchMapper;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Registrar como acción de tabla o de página en PayrollRunResource:
 *
 *   use App\Filament\Resources\PayrollRunResource\Actions\GenerateBbvaFileAction;
 *   ...
 *   ->actions([ GenerateBbvaFileAction::make(), ... ])
 */
class GenerateBbvaFileAction
{
    public static function make(): Action
    {
        return Action::make('generar_archivo_bbva')
            ->label('Generar archivo BBVA')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (PayrollRun $record) => $record->estado === 'aprobada')
            ->requiresConfirmation()
            ->modalDescription('Se generará el archivo de carga masiva para BBVA con los trabajadores que tengan cuenta bancaria registrada.')
            ->action(function (PayrollRun $record) {
                try {
                    [$batch, $warnings] = (new PayrollRunToBbvaBatchMapper())->map($record);

                    if (empty($batch->beneficiaries)) {
                        Notification::make()
                            ->title('No hay beneficiarios válidos para generar el archivo')
                            ->body(implode("\n", $warnings))
                            ->danger()
                            ->send();

                        return;
                    }

                    $contents = (new BbvaHaberesExporter())->export($batch);

                    $fileName = sprintf(
                        'pagos/bbva/%s_%s_Q%s_%s.txt',
                        $record->company->ruc,
                        $record->anio,
                        $record->quincena ?? $record->mes,
                        now()->format('YmdHis')
                    );

                    Storage::disk('local')->put($fileName, $contents);

                    $record->paymentFiles()->create([
                        'banco' => 'BBVA',
                        'archivo_path' => $fileName,
                        'cantidad_registros' => count($batch->beneficiaries),
                        'monto_total' => $batch->montoTotal(),
                        'generado_por' => Auth::id(),
                    ]);

                    $title = 'Archivo BBVA generado: ' . count($batch->beneficiaries) . ' registros por S/ ' . number_format($batch->montoTotal(), 2);

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
