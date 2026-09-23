<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiquidacionCeseResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Employee;
use App\Models\LiquidacionCese;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LiquidacionCeseResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = LiquidacionCese::class;
    protected static ?string $slug             = 'liquidaciones-cese';
    protected static ?string $navigationIcon   = 'heroicon-o-arrow-right-start-on-rectangle';
    protected static ?string $navigationLabel  = 'Liquidación por Cese';
    protected static ?string $navigationGroup  = 'Beneficios Sociales';
    protected static ?int    $navigationSort   = 30;
    protected static ?string $modelLabel       = 'Liquidación por cese';
    protected static ?string $pluralModelLabel = 'Liquidaciones por Cese';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Trabajador')
                    ->getStateUsing(fn ($record) => $record->apellidos . ', ' . $record->nombres)
                    ->searchable(query: fn ($query, $search) => $query->where('apellidos', 'like', "%$search%")->orWhere('nombres', 'like', "%$search%"))
                    ->sortable(['apellidos']),

                Tables\Columns\TextColumn::make('fecha_cese')
                    ->label('Fecha de cese')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('motivo_label')
                    ->label('Motivo')
                    ->badge()
                    ->color(fn ($record) => $record->motivo_cese === 'despido_arbitrario' ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('monto_vacaciones_truncas')
                    ->label('Vacac. truncas')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('remuneracion_vacacional_pendiente')
                    ->label('Remun. vacacional')
                    ->money('PEN')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('indemnizacion_vacacional')
                    ->label('Indemniz. vacacional')
                    ->money('PEN')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monto_gratificacion_trunca')
                    ->label('Grat. trunca')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monto_cts_trunca')
                    ->label('CTS trunca')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('indemnizacion')
                    ->label('Indemnización')
                    ->money('PEN')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monto_total')
                    ->label('Total liquidación')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('motivo_cese')
                    ->label('Motivo')
                    ->options([
                        'renuncia' => 'Renuncia voluntaria',
                        'despido_justificado' => 'Despido justificado',
                        'despido_arbitrario' => 'Despido arbitrario',
                        'mutuo_disenso' => 'Mutuo disenso',
                        'terminacion_contrato' => 'Término de contrato',
                    ]),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_liquidacion_cese')
                    ->label('Calcular liquidación por cese')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('Trabajador')
                            ->relationship('employee', 'apellidos')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre_completo . ($record->fecha_cese ? ' — cese: ' . $record->fecha_cese->format('d/m/Y') : ' (sin fecha de cese registrada)'))
                            ->searchable()
                            ->required()
                            ->helperText('Solo aparecen calculables los trabajadores con fecha de cese registrada en su ficha (pestaña "Datos Laborales").'),

                        Forms\Components\Select::make('motivo_cese')
                            ->label('Motivo del cese')
                            ->options([
                                'renuncia' => 'Renuncia voluntaria',
                                'despido_justificado' => 'Despido justificado (con causa)',
                                'despido_arbitrario' => 'Despido arbitrario (sin causa) — genera indemnización',
                                'mutuo_disenso' => 'Mutuo disenso',
                                'terminacion_contrato' => 'Término de contrato (plazo fijo)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Solo "Despido arbitrario" calcula indemnización automáticamente (1.5 sueldos por año, tope 12 sueldos).'),

                        Forms\Components\TextInput::make('indemnizacion_manual')
                            ->label('Indemnización manual (S/) — opcional')
                            ->numeric()
                            ->prefix('S/')
                            ->helperText('Déjalo vacío para que se calcule automáticamente según el motivo. Ingresa un monto solo si hay un acuerdo/transacción distinto al cálculo legal estándar.'),

                        Forms\Components\Placeholder::make('aviso')
                            ->label('')
                            ->content('Calcula vacaciones truncas, gratificación trunca + bonificación 9%, y CTS trunca. NO incluye la remuneración del mes de cese — eso se calcula con "Calcular planilla" en Liquidación de Planilla, usando los días trabajados hasta el cese.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            app(PlanillaService::class)->calcularLiquidacionCese(
                                $data['employee_id'],
                                $data['motivo_cese'],
                                filled($data['indemnizacion_manual'] ?? null) ? (float) $data['indemnizacion_manual'] : null,
                            );

                            Notification::make()
                                ->title('Liquidación por cese calculada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al calcular')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('fecha_cese', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiquidacionesCese::route('/'),
        ];
    }
}
