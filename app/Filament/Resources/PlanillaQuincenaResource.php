<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanillaQuincenaResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\PlanillaQuincena;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanillaQuincenaResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = PlanillaQuincena::class;
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Quincena (día 15)';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 10;
    protected static ?string $modelLabel       = 'Quincena';
    protected static ?string $pluralModelLabel = 'Adelantos de Quincena';

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

                Tables\Columns\TextColumn::make('mes_nombre')
                    ->label('Periodo')
                    ->sortable(['periodo'])
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sueldo_base')
                    ->label('Sueldo base')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('asignacion_familiar')
                    ->label('Asig. familiar')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('base_quincenal')
                    ->label('Base ÷ 2')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('descuento_pension')
                    ->label('AFP/ONP (÷2)')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('descuento_5ta_categoria')
                    ->label('Renta 5ta (÷2)')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('neto_pagar')
                    ->label('Neto a depositar')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(function () {
                        if (!PlanillaQuincena::exists()) return [];
                        return PlanillaQuincena::distinct()
                            ->orderByDesc('periodo')
                            ->pluck('mes_nombre', 'periodo')
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_quincena')
                    ->label('Calcular quincena')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('periodo')
                            ->label('Periodo')
                            ->options(static::generarOpcionesPeriodo())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(fn () => \App\Models\Company::where('pago_quincenal', true)->pluck('razon_social', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Solo se listan empresas con "Paga quincenal" activado en su ficha.'),

                        Forms\Components\Placeholder::make('aviso')
                            ->label('')
                            ->content('Base = sueldo_base + asignación familiar (si aplica). Sobre esa base se calcula AFP/ONP y renta 5ta, todo dividido entre 2. Nada de horas extra, tardanzas ni comisiones. Al calcular la Liquidación mensual de fin de mes, este monto se resta automáticamente del neto para no pagarlo dos veces.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $resultado = app(PlanillaService::class)->calcularPeriodoQuincena(
                                $data['company_id'],
                                $data['periodo'],
                            );

                            Notification::make()
                                ->title("Quincena calculada: {$resultado->count()} trabajadores")
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

                Tables\Actions\Action::make('exportar')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('periodo')
                            ->label('Periodo')
                            ->options(function () {
                                if (!PlanillaQuincena::exists()) return [];
                                return PlanillaQuincena::distinct()
                                    ->orderByDesc('periodo')
                                    ->pluck('mes_nombre', 'periodo')
                                    ->toArray();
                            })
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->route('quincena.exportar', [
                            'periodo'    => $data['periodo'],
                            'company_id' => $data['company_id'],
                        ]);
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanillaQuincenas::route('/'),
        ];
    }

    private static function generarOpcionesPeriodo(): array
    {
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo',    '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto',  '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        $options = [];
        for ($i = -6; $i <= 1; $i++) {
            $date  = now()->addMonths($i);
            $key   = $date->format('Y-m');
            $mes   = $meses[$date->format('m')];
            $options[$key] = "$mes {$date->year}";
        }
        return array_reverse($options, true);
    }
}
