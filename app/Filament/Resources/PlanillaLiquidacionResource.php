<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanillaLiquidacionResource\Pages;
use App\Models\PlanillaLiquidacion;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PlanillaLiquidacionResource extends Resource
{
    protected static ?string $model            = PlanillaLiquidacion::class;
    protected static ?string $navigationIcon   = 'heroicon-o-calculator';
    protected static ?string $navigationLabel  = 'Planilla';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 20;
    protected static ?string $modelLabel       = 'Liquidacion';
    protected static ?string $pluralModelLabel = 'Planilla mensual';

    // No hay form de creacion manual — se genera desde la accion "Calcular"
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Bono especial')
                ->schema([
                    Forms\Components\TextInput::make('bonos_especiales')
                        ->label('Bono especial (S/)')
                        ->numeric()
                        ->prefix('S/')
                        ->default(0)
                        ->helperText('Ingreso extraordinario para este empleado en el periodo.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Empleado')
                    ->getStateUsing(fn ($record) => $record->apellidos . ', ' . $record->nombres)
                    ->searchable(query: fn ($q, $s) => $q->where('apellidos', 'like', "%$s%")->orWhere('nombres', 'like', "%$s%"))
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

                Tables\Columns\TextColumn::make('dias_falta')
                    ->label('Faltas')
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('total_minutos_tarde')
                    ->label('Min. tarde')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' min' : '—')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('remuneracion_bruta')
                    ->label('Bruto')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('descuento_pension')
                    ->label('Pension')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_descuentos')
                    ->label('Total desc.')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('neto_pagar')
                    ->label('Neto a pagar')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('sistema_pensiones_label')
                    ->label('Pension')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')
    ->label('Periodo')
    ->options(function () {
        if (!PlanillaLiquidacion::exists()) return [];
        return PlanillaLiquidacion::distinct()
            ->orderByDesc('periodo')
            ->pluck('mes_nombre', 'periodo')
            ->toArray();
    }),
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_planilla')
                    ->label('Calcular planilla')
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
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable(),

                        Forms\Components\Repeater::make('bonos')
                            ->label('Bonos especiales (opcional)')
                            ->schema([
                                Forms\Components\Select::make('employee_id')
                                    ->label('Empleado')
                                    ->relationship('employee', 'apellidos')
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('monto')
                                    ->label('Monto S/')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar bono')
                            ->collapsible(),
                        
                        Forms\Components\Repeater::make('descuentos_varios')
                        ->label('Descuentos varios (préstamos, adelantos, quincenas)')
                        ->schema([
                            Forms\Components\Select::make('employee_id')
                                ->label('Empleado')
                                ->relationship('employee', 'apellidos')
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('monto')
                                ->label('Monto S/')
                                ->numeric()
                                ->prefix('S/')
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar descuento')
                        ->collapsible()
                        ->helperText('No afecta EsSalud, AFP/ONP ni 5ta categoría — se resta directo del neto a pagar.'),

                    ])
                    ->action(function (array $data) {
                        $bonos = collect($data['bonos'] ?? [])
                            ->keyBy('employee_id')
                            ->map(fn ($b) => floatval($b['monto']))
                            ->toArray();
                        $descuentos = collect($data['descuentos_varios'] ?? [])
                            ->keyBy('employee_id')
                            ->map(fn ($d) => floatval($d['monto']))
                            ->toArray();

                        try {
                            $liquidaciones = app(PlanillaService::class)->calcularPeriodo(
                                $data['company_id'],
                                $data['periodo'],
                                $bonos,
                                $descuentos,
                            );

                            Notification::make()
                                ->title('Planilla calculada')
                                ->body("{$liquidaciones->count()} empleados procesados.")
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


                Tables\Actions\Action::make('generar_boletas')
    ->label('Generar boletas')
    ->icon('heroicon-o-document-duplicate')
    ->color('warning')
    ->form([
        Forms\Components\Select::make('periodo')
            ->label('Periodo')
            ->options(function () {
                if (!PlanillaLiquidacion::exists()) return [];
                return PlanillaLiquidacion::distinct()
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
        return redirect()->route('boletas.exportar', [
            'periodo'   => $data['periodo'],
            'companyId' => $data['company_id'],
        ]);
    }),
     
Tables\Actions\Action::make('exportar_plame')
    ->label('Exportar PLAME')
    ->icon('heroicon-o-document-arrow-down')
    ->color('gray')
    ->form([
        Forms\Components\Select::make('periodo')
            ->label('Periodo')
            ->options(function () {
                if (!PlanillaLiquidacion::exists()) return [];
                return PlanillaLiquidacion::distinct()
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
 
        Forms\Components\Placeholder::make('aviso')
            ->label('')
            ->content('⚠️ Antes de subir este archivo al PDT PLAME, ábrelo primero como prueba en un entorno de práctica de SUNAT. Varios conceptos (gratificaciones, CTS, utilidades) aún se declaran en 0 porque esos módulos están pendientes de implementar.'),
    ])
    ->action(function (array $data) {
        return redirect()->route('plame.exportar', [
            'periodo'   => $data['periodo'],
            'companyId' => $data['company_id'],
        ]);
    }),
 

                Tables\Actions\Action::make('exportar')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('periodo')
                            ->label('Periodo')
                            ->options(function () {
    if (!PlanillaLiquidacion::exists()) return [];
    return PlanillaLiquidacion::distinct()
        ->orderByDesc('periodo')
        ->pluck('mes_nombre', 'periodo')
        ->toArray();
})                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->route('planilla.exportar', [
                            'periodo'    => $data['periodo'],
                            'company_id' => $data['company_id'],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Detalle')
                    ->icon('heroicon-o-document-text')
                    ->url(fn ($record) => static::getUrl('detalle', ['record' => $record])),
                Tables\Actions\Action::make('boleta_pdf')
                    ->label('Boleta PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn ($record) => route('boletas.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->label('Bono')
                    ->icon('heroicon-o-plus-circle')
                    ->successNotificationTitle('Bono actualizado'),
            ])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListPlanillaLiquidaciones::route('/'),
            'detalle' => Pages\DetallePlanillaLiquidacion::route('/{record}/detalle'),
            'edit'    => Pages\EditPlanillaLiquidacion::route('/{record}/edit'),
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
