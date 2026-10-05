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
use App\Filament\Traits\HasCompanyScope;

class PlanillaLiquidacionResource extends Resource
{
    use HasCompanyScope;
    
    protected static ?string $model            = PlanillaLiquidacion::class;
    protected static ?string $navigationIcon   = 'heroicon-o-calculator';
    protected static ?string $navigationLabel  = 'Liquidación de Planilla';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 5;
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
                        ->helperText('Ingreso extraordinario para este trabajador en el periodo.'),
                ]),
        ]);
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

                Tables\Columns\TextColumn::make('adelanto_quincena')
                    ->label('Ya adelantado (Q)')
                    ->getStateUsing(fn ($record) => $record->company?->pago_quincenal ? $record->adelanto_quincena : null)
                    ->placeholder('—')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('gray')
                    ->toggleable(),

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
                                    ->label('Trabajador')
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
                            ->label('Descuentos varios')
                            ->schema([
                                Forms\Components\Select::make('employee_id')
                                    ->label('Trabajador')
                                    ->relationship('employee', 'apellidos')
                                    ->searchable()
                                    ->live()
                                    ->required(),
                                Forms\Components\Select::make('tipo')
                                    ->label('Tipo')
                                    ->options([
                                        'adelanto' => 'Adelanto (código PLAME 0701)',
                                        'otros'    => 'Préstamo / Otros (código PLAME 0706)',
                                    ])
                                    ->required()
                                    ->native(false),
                                Forms\Components\TextInput::make('monto')
                                    ->label('Monto S/')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required(),
                                Forms\Components\Placeholder::make('aviso_quincena')
                                    ->label('Ya pagado por quincena este periodo')
                                    ->columnSpanFull()
                                    ->content(function (Forms\Get $get) {
                                        $employeeId = $get('employee_id');
                                        $periodo    = $get('../../periodo');

                                        if (! $employeeId || ! $periodo) {
                                            return '—';
                                        }

                                        $yaPagado = \App\Models\PlanillaQuincena::where('employee_id', $employeeId)
                                            ->where('periodo', $periodo)
                                            ->value('neto_pagar');

                                        if (! $yaPagado) {
                                            return 'No tiene quincena calculada este periodo.';
                                        }

                                        return "S/ " . number_format((float) $yaPagado, 2)
                                            . " — si el \"Adelanto\" que vas a ingresar es ese mismo pago de quincena, NO lo repitas aquí (el sistema ya lo descuenta solo del neto).";
                                    }),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar descuento')
                            ->collapsible()
                            ->helperText('No afecta EsSalud, AFP/ONP ni 5ta categoría — se resta directo del neto a pagar. OJO: la quincena ya pagada se descuenta sola, no la vuelvas a poner aquí como "Adelanto" (revisa el aviso que aparece al elegir el trabajador).'),
                        Forms\Components\Repeater::make('subsidios')
                            ->label('Subsidios EsSalud')
                            ->schema([
                                Forms\Components\Select::make('employee_id')
                                    ->label('Trabajador')
                                    ->relationship('employee', 'apellidos')
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('tipo')
                                    ->label('Tipo')
                                    ->options([
                                        'enfermedad' => 'Subsidio por enfermedad (código 0916)',
                                        'maternidad' => 'Subsidio por maternidad (código 0915)',
                                    ])
                                    ->required()
                                    ->native(false),
                                Forms\Components\TextInput::make('monto')
                                    ->label('Monto S/')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar subsidio')
                            ->collapsible()
                            ->helperText('No afecta EsSalud ni ONP — SÍ afecta la base de AFP (aporte, comisión, prima).'),

                        Forms\Components\Repeater::make('retenciones_5ta_manual')
                            ->label('Retención 5ta categoría manual (excepcional)')
                            ->schema([
                                Forms\Components\Select::make('employee_id')
                                    ->label('Trabajador')
                                    ->relationship('employee', 'apellidos')
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('monto')
                                    ->label('Monto S/')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required()
                                    ->helperText('Puede ser 0.'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar retención manual')
                            ->collapsible()
                            ->helperText('Usar SOLO mientras el sistema no tiene histórico de ingresos previo (2026, primer año de uso). Reemplaza el cálculo automático para ese trabajador ese mes. A partir de enero 2027, dejar vacío para que el sistema calcule solo.'),

                    ])
                    ->action(function (array $data) {
                        $bonos = collect($data['bonos'] ?? [])
                            ->keyBy('employee_id')
                            ->map(fn ($b) => floatval($b['monto']))
                            ->toArray();
                        $descuentos = collect($data['descuentos_varios'] ?? [])
                            ->where('tipo', 'otros')
                            ->keyBy('employee_id')
                            ->map(fn ($d) => floatval($d['monto']))
                            ->toArray();

                        $adelantos = collect($data['descuentos_varios'] ?? [])
                            ->where('tipo', 'adelanto')
                            ->keyBy('employee_id')
                            ->map(fn ($d) => floatval($d['monto']))
                            ->toArray();
                        
                            $subsidiosEnfermedad = collect($data['subsidios'] ?? [])
                            ->where('tipo', 'enfermedad')
                            ->keyBy('employee_id')
                            ->map(fn ($s) => floatval($s['monto']))
                            ->toArray();

                        $subsidiosMaternidad = collect($data['subsidios'] ?? [])
                            ->where('tipo', 'maternidad')
                            ->keyBy('employee_id')
                            ->map(fn ($s) => floatval($s['monto']))
                            ->toArray();

                        $retencionesManuales5ta = collect($data['retenciones_5ta_manual'] ?? [])
                            ->keyBy('employee_id')
                            ->map(fn ($r) => floatval($r['monto']))
                            ->toArray();

                        try {
                            $liquidaciones = app(PlanillaService::class)->calcularPeriodo(
                                $data['company_id'],
                                $data['periodo'],
                                $bonos,
                                $descuentos,
                                $adelantos,
                                $subsidiosEnfermedad,
                                $subsidiosMaternidad,
                                $retencionesManuales5ta,
                            );

                            Notification::make()
                                ->title('Planilla calculada')
                                ->body("{$liquidaciones->count()} trabajadores procesados.")
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
 

                Tables\Actions\Action::make('exportar_afpnet')
                    ->label('Exportar AFP (AFPnet)')
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

                        Forms\Components\Placeholder::make('aviso_afpnet')
                            ->label('')
                            ->content('Archivo para AFPnet: solo trabajadores afiliados a AFP, sin cabeceras, con el CUSPP, los ingresos afectos y las marcas S/N de inicio/cese de relación laboral.'),
                    ])
                    ->action(function (array $data) {
                        $sinCuspp = PlanillaLiquidacion::with('employee')
                            ->where('periodo', $data['periodo'])
                            ->where('company_id', $data['company_id'])
                            ->where('sistema_pensiones', 'like', 'afp_%')
                            ->get()
                            ->filter(fn ($l) => $l->employee && blank($l->employee->cuspp))
                            ->map(fn ($l) => $l->apellidos . ', ' . $l->nombres);

                        if ($sinCuspp->isNotEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Trabajadores sin CUSPP en el archivo')
                                ->body('Quedaron en blanco: ' . $sinCuspp->implode('; ') . '. Complétalos en la ficha si ya tienen CUSPP.')
                                ->warning()
                                ->persistent()
                                ->send();
                        }

                        return redirect()->route('afpnet.exportar', [
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
                Tables\Actions\Action::make('subir_firmada')
        ->label(fn ($record) => $record->tiene_boleta_firmada ? 'Reemplazar firmada' : 'Subir boleta firmada')
        ->icon('heroicon-o-pencil-square')
        ->color(fn ($record) => $record->tiene_boleta_firmada ? 'gray' : 'warning')
        ->form([
            Forms\Components\FileUpload::make('archivo')
                ->label('PDF firmado con DNIe')
                ->acceptedFileTypes(['application/pdf'])
                ->directory('boletas-firmadas')
                ->required()
                ->helperText('Sube el mismo PDF de la boleta, ya firmado digitalmente por el gerente general con su DNIe.'),
        ])
        ->action(function (\App\Models\PlanillaLiquidacion $record, array $data) {
            $record->update([
                'boleta_firmada_path' => $data['archivo'],
                'boleta_firmada_at'   => now(),
                'boleta_firmada_por'  => auth()->id(),
            ]);
 
            \Filament\Notifications\Notification::make()
                ->title('Boleta firmada guardada')
                ->success()
                ->send();
        }),
 
    Tables\Actions\Action::make('descargar_firmada')
        ->label('Descargar firmada')
        ->icon('heroicon-o-shield-check')
        ->color('success')
        ->visible(fn ($record) => $record->tiene_boleta_firmada)
        ->url(fn ($record) => \Illuminate\Support\Facades\Storage::disk('public')->url($record->boleta_firmada_path))
        ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->label('Bono')
                    ->icon('heroicon-o-plus-circle')
                    ->successNotificationTitle('Bono actualizado'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar este cálculo de planilla')
                    ->modalDescription('Se borra el cálculo de este trabajador para este periodo (no afecta a los demás). Útil para corregir un error (ej. un "Adelanto" duplicado) y volver a calcular desde cero con "Calcular planilla".')
                    ->successNotificationTitle('Cálculo eliminado'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
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
