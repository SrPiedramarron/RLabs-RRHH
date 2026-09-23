<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\PayrollConcept;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\PlanillaLiquidacion;
use App\Models\PlanillaQuincena;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                Tables\Actions\Action::make('importar_liquidacion')
                    ->label('Importar de Liquidación/Quincena')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        /** @var PayrollRun $run */
                        $run = $this->getOwnerRecord();

                        return $run->periodo_tipo === 'quincenal'
                            ? 'Trae el neto a pagar ya calculado en "Quincena (día 15)" para este mes/empresa. Sobrescribe el monto de los trabajadores que ya estén en esta planilla.'
                            : 'Trae el neto a pagar ya calculado en "Liquidación de Planilla" para este mes/empresa. Sobrescribe el monto de los trabajadores que ya estén en esta planilla.';
                    })
                    ->action(function () {
                        /** @var PayrollRun $run */
                        $run = $this->getOwnerRecord();
                        $periodo = sprintf('%d-%02d', $run->anio, $run->mes);

                        $origen = $run->periodo_tipo === 'quincenal'
                            ? PlanillaQuincena::query()->where('company_id', $run->company_id)->where('periodo', $periodo)->get()
                            : PlanillaLiquidacion::query()->where('company_id', $run->company_id)->where('periodo', $periodo)->get();

                        if ($origen->isEmpty()) {
                            Notification::make()
                                ->title('No hay nada calculado para ese periodo')
                                ->body($run->periodo_tipo === 'quincenal'
                                    ? 'Primero usa "Calcular quincena" en Quincena (día 15) para ' . $periodo . '.'
                                    : 'Primero usa "Calcular planilla" en Liquidación de Planilla para ' . $periodo . '.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $importados = 0;
                        $omitidos = 0;

                        foreach ($origen as $liquidacion) {
                            if ((float) $liquidacion->neto_pagar <= 0) {
                                $omitidos++;

                                continue;
                            }

                            PayrollEntry::updateOrCreate(
                                ['payroll_run_id' => $run->id, 'employee_id' => $liquidacion->employee_id],
                                ['monto_neto' => $liquidacion->neto_pagar]
                            );

                            $importados++;
                        }

                        Notification::make()
                            ->title("Importados {$importados} trabajadores" . ($omitidos ? " ({$omitidos} con neto en cero, omitidos)" : ''))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Agregar trabajador manualmente')
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
            ->emptyStateDescription('Usa "Importar de Liquidación/Quincena" para traer los netos ya calculados, o agrega trabajadores manualmente.');
    }
}
