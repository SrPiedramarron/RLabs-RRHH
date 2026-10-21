<?php
namespace App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\Concerns\SyncsEmployeeDevices;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    use SyncsEmployeeDevices;

    protected static string $resource = EmployeeResource::class;

    /**
     * Muestra el nombre del trabajador junto al título, para que no se
     * pierda de vista al cambiar de pestaña (reportado por RRHH, set. 2026:
     * al editar y cambiar de pestaña ya no se veía a quién se estaba
     * editando).
     */
    public function getTitle(): string
    {
        return 'Editar Trabajador — ' . $this->record->nombre_completo;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('certificado_trabajo')
                    ->label(fn () => $this->record->fecha_cese ? 'Certificado de trabajo' : 'Constancia de trabajo')
                    ->icon('heroicon-o-document-text')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)->default(now())->required(),
                    ])
                    ->action(fn (array $data) => redirect()->route('documentos.certificado-trabajo', ['employee' => $this->record->id, 'fecha' => $data['fecha']])),

                Actions\Action::make('carta_retiro_cts')
                    ->label('Carta de retiro de CTS')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn () => (bool) $this->record->fecha_cese)
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)
                            ->default(fn () => $this->record->fecha_cese)->required()
                            ->helperText('Por defecto es la fecha de cese.'),
                    ])
                    ->action(fn (array $data) => redirect()->route('documentos.carta-retiro-cts', ['employee' => $this->record->id, 'fecha' => $data['fecha']])),

                Actions\Action::make('certificado_quinta_auto')
                    ->label('Certificado de 5.ª categoría (automático)')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $anio = $this->record->fecha_cese?->year ?? now()->year;
                        $p = app(\App\Services\DocumentosLaboralesService::class)->quintaPrefill($this->record, $anio);

                        return 'Se genera con lo que el sistema tiene de ' . $anio . ' (planillas, histórico, gratificaciones y liquidación).'
                            . ($p['meses_sin_datos'] ? ' OJO: no hay datos de ' . implode(', ', $p['meses_sin_datos']) . '; en ese caso usa «Certificado de 5.ª categoría» para completarlos a mano.' : '');
                    })
                    ->action(fn () => redirect()->route('documentos.certificado-quinta', ['employee' => $this->record->id, 'anio' => $this->record->fecha_cese?->year ?? now()->year])),

                Actions\Action::make('certificado_quinta')
                    ->label('Certificado de 5.ª categoría (revisar y ajustar)')
                    ->icon('heroicon-o-receipt-percent')
                    ->modalDescription('Los montos salen de lo que el sistema tiene del ejercicio (planillas, histórico, gratificaciones y liquidación). Revísalos y ajusta lo que haga falta antes de emitir.')
                    ->form(function () {
                        $anio = $this->record->fecha_cese?->year ?? now()->year;
                        $p    = app(\App\Services\DocumentosLaboralesService::class)->quintaPrefill($this->record, $anio);

                        $monto = fn (string $k, string $label) => \Filament\Forms\Components\TextInput::make($k)->label($label)->numeric()->prefix('S/')->default($p[$k])->required();

                        return [
                            \Filament\Forms\Components\TextInput::make('anio')->label('Ejercicio')->numeric()->default($anio)->required(),
                            \Filament\Forms\Components\Section::make('Rentas brutas')->columns(2)->schema([
                                $monto('sueldos', 'Sueldos y salarios'),
                                $monto('comisiones', 'Comisiones'),
                                $monto('utilidades', 'Participación en las utilidades'),
                                $monto('gratificacion', 'Gratificación'),
                                $monto('bonificaciones', 'Bonificaciones extraordinarias'),
                                $monto('remu_vacacional', 'Remuneración vacacional'),
                                $monto('vac_truncas', 'Vacaciones truncas'),
                                $monto('grat_trunca', 'Gratificación trunca'),
                            ]),
                            \Filament\Forms\Components\Section::make('Deducción y retenciones')->columns(3)->schema([
                                $monto('deduccion', '7 UIT (deducción)'),
                                $monto('retenciones', 'Retenciones informadas'),
                                $monto('devolucion', 'Devolución informada'),
                            ]),
                            \Filament\Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)->default(now())->required(),
                        ];
                    })
                    ->action(fn (array $data) => redirect()->route('documentos.certificado-quinta', array_merge(['employee' => $this->record->id], $data))),
            ])->label('Documentos')->icon('heroicon-o-document-duplicate')->button(),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * El acceso remoto (contraseña / activo / resetear al DNI) ya NO se
     * maneja aquí — son botones de acción independientes en el propio
     * formulario (ver EmployeeResource::form(), sección "Acceso Remoto"),
     * porque depender del guardado general perdía el acceso en cada edición
     * (bug reportado por RRHH, set. 2026): el toggle "Acceso activo" se
     * enviaba en false aunque se viera activado, y el campo de contraseña
     * nueva tenía dehydrated(false) por lo que nunca llegaba a guardarse.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $sedesAdicionales = $data['sedes_adicionales'] ?? [];
        unset($data['sedes_adicionales']);

        $record->fill($data)->save();

        $this->syncSedesAdicionales($record, $sedesAdicionales);

        return $record;
    }
}
