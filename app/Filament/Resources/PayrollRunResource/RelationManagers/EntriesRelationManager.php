<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\PayrollConcept;
use App\Models\PayrollEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Trabajadores en la planilla';

    protected static ?string $icon = 'heroicon-o-users';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->label('Trabajador')
                ->relationship('employee', 'apellidos')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre_completo)
                ->searchable()
                ->required()
                ->disabledOn('edit'),

            Forms\Components\Repeater::make('lines')
                ->relationship('lines')
                ->label('Conceptos')
                ->schema([
                    Forms\Components\Select::make('payroll_concept_id')
                        ->label('Concepto')
                        ->options(PayrollConcept::query()->where('active', true)->pluck('nombre', 'id'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre . ' (' . ($record->tipo === 'ingreso' ? 'Ingreso' : 'Descuento') . ')')
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('monto')
                        ->label('Monto')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->prefix('S/'),
                ])
                ->columns(2)
                ->addActionLabel('Agregar concepto')
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee_id')
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Conceptos')
                    ->counts('lines')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('monto_neto')
                    ->label('Neto a pagar')
                    ->money('PEN')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn (PayrollEntry $record) => $record->recalcularNeto()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn (PayrollEntry $record) => $record->recalcularNeto()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin trabajadores en esta planilla')
            ->emptyStateDescription('Agrega trabajadores y sus conceptos (ingresos/descuentos) para calcular el neto a pagar.');
    }
}
