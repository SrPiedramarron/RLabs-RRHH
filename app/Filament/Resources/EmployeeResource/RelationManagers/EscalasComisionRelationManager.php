<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EscalasComisionRelationManager extends RelationManager
{
    protected static string $relationship = 'escalasComision';

    protected static ?string $title = 'Escalas de Comisión';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('monto_desde')
                ->label('Monto desde (S/)')
                ->numeric()
                ->required()
                ->helperText('Si las ventas del periodo alcanzan este monto (o más), se aplica el % de esta fila. Se usa el tramo más alto alcanzado — no es acumulativo por partes.'),

            Forms\Components\TextInput::make('porcentaje')
                ->label('Porcentaje (%)')
                ->numeric()
                ->step(0.01)
                ->suffix('%')
                ->required()
                ->formatStateUsing(fn ($state) => $state !== null ? round($state * 100, 4) : null)
                ->dehydrateStateUsing(fn ($state) => filled($state) ? $state / 100 : null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('monto_desde')
            ->columns([
                Tables\Columns\TextColumn::make('monto_desde')
                    ->label('Monto desde')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('porcentaje')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn ($state) => round($state * 100, 4) . '%')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('monto_desde')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin escalas configuradas')
            ->emptyStateDescription('Si no hay escalas, se usa el porcentaje fijo de la ficha del trabajador.');
    }
}
