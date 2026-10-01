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
                    ->modalDescription('Se borra este registro del historial y se recalcula automáticamente la fecha de última vacación, el saldo pendiente y los días tomados del trabajador (incluyendo lo que se ve en "Control de Vacaciones").')
                    ->after(function ($record) {
                        $empleado = $record->employee;
                        if ($empleado) {
                            app(\App\Services\VacacionesService::class)->recalcularDesdeHistorial($empleado);
                        }
                    }),
            ])
            ->defaultSort('fecha_inicio', 'desc')
            ->emptyStateHeading('Sin vacaciones registradas')
            ->emptyStateDescription('Este trabajador aún no tiene vacaciones registradas en el sistema.');
    }
}
