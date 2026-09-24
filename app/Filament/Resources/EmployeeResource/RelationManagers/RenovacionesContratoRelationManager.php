<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RenovacionesContratoRelationManager extends RelationManager
{
    protected static string $relationship = 'renovacionesContrato';

    protected static ?string $title = 'Historial de Renovaciones de Contrato';

    protected static ?string $icon = 'heroicon-o-arrow-path';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_renovacion')
            ->columns([
                Tables\Columns\TextColumn::make('numero_renovacion')
                    ->label('Renovación')
                    ->formatStateUsing(fn ($state) => "N° {$state}")
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('fecha_fin_anterior')
                    ->label('Fin anterior')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('fecha_fin_nueva')
                    ->label('Fin nuevo')
                    ->date('d/m/Y')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('observacion')
                    ->label('Observación')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('renovadoPor.name')
                    ->label('Renovado por')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('renovado_at')
                    ->label('Fecha de renovación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalDescription('Esto solo borra el registro del historial — no revierte la fecha fin de contrato ni la fecha de cese del trabajador.'),
            ])
            ->defaultSort('numero_renovacion', 'desc')
            ->emptyStateHeading('Sin renovaciones registradas')
            ->emptyStateDescription('Este trabajador aún no tiene renovaciones de contrato en el sistema.');
    }
}
