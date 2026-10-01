<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VacacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'vacaciones';

    protected static ?string $title = 'Historial de Vacaciones';

    protected static ?string $icon = 'heroicon-o-sun';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fecha_inicio')
            ->columns([
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias')
                    ->label('Días')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('observacion')
                    ->label('Observación')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('registradoPor.name')
                    ->label('Registrado por')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalDescription('Se borra este registro del historial, se recalcula el saldo del trabajador (lo que se ve en "Control de Vacaciones") y se quita la marca de "vacaciones" en Registro de Asistencia para esos días, reprocesando sus marcaciones reales si las hay.')
                    ->after(function ($record) {
                        $empleado = $record->employee;
                        if (!$empleado) {
                            return;
                        }

                        // Quita la marca 'vacaciones' puesta en Registro de
                        // Asistencia para este rango — antes quedaba como
                        // vacaciones para siempre aunque se borrara el
                        // historial (reportado por Cielo, oct. 2026).
                        \App\Models\AttendanceRecord::where('employee_id', $empleado->id)
                            ->where('estado', 'vacaciones')
                            ->whereBetween('fecha', [$record->fecha_inicio->toDateString(), $record->fecha_fin->toDateString()])
                            ->delete();

                        // Si había marcaciones reales del reloj esos días,
                        // las vuelve a calcular en vez de dejar el día vacío.
                        app(\App\Services\AttendanceProcessor::class)->reprocesarRango(
                            $record->fecha_inicio->toDateString(),
                            $record->fecha_fin->toDateString()
                        );

                        app(\App\Services\VacacionesService::class)->recalcularDesdeHistorial($empleado);
                    }),
            ])
            ->defaultSort('fecha_inicio', 'desc')
            ->emptyStateHeading('Sin vacaciones registradas')
            ->emptyStateDescription('Este trabajador aún no tiene vacaciones registradas en el sistema.');
    }
}
