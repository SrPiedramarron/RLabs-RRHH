<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ControlVacacionesResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\VacacionesService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ControlVacacionesResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-sun';
    protected static ?string $navigationLabel = 'Control de Vacaciones';
    protected static ?string $modelLabel = 'Vacaciones';
    protected static ?string $pluralModelLabel = 'Control de Vacaciones';
    protected static ?string $navigationGroup = 'Planilla';
    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false; // solo lectura, el saldo se carga por import
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Employee::where('active', true)
                    ->when(\App\Helpers\CompanyContext::get(), fn ($q) => $q->where('company_id', \App\Helpers\CompanyContext::get()))
            )
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Empleado')
                    ->getStateUsing(fn ($record) => $record->apellidos . ', ' . $record->nombres)
                    ->searchable(query: fn ($query, $search) => $query->where('apellidos', 'like', "%$search%")->orWhere('nombres', 'like', "%$search%")),

                Tables\Columns\TextColumn::make('saldo_vacaciones_inicial')
                    ->label('Saldo inicial')
                    ->numeric(1)
                    ->suffix(' días')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fecha_saldo_vacaciones')
                    ->label('Corte')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('dias_generados')
                    ->label('Generados desde corte')
                    ->getStateUsing(function ($record) {
                        $r = app(VacacionesService::class)->calcularSaldo($record);
                        return $r['dias_generados'] ?? '—';
                    })
                    ->suffix(' días'),

                Tables\Columns\TextColumn::make('dias_tomados')
                    ->label('Tomados desde corte')
                    ->getStateUsing(function ($record) {
                        $r = app(VacacionesService::class)->calcularSaldo($record);
                        return $r['dias_tomados'] ?? '—';
                    })
                    ->suffix(' días')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('saldo_actual')
                    ->label('Saldo actual')
                    ->getStateUsing(function ($record) {
                        $r = app(VacacionesService::class)->calcularSaldo($record);
                        return $r['saldo_actual'] ?? 'Sin cargar';
                    })
                    ->suffix(fn ($state) => $state !== 'Sin cargar' ? ' días' : '')
                    ->weight('bold')
                    ->color(fn ($state) => match (true) {
                        $state === 'Sin cargar' => 'gray',
                        (float) $state <= 0     => 'danger',
                        (float) $state < 15      => 'warning',
                        default                   => 'success',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('registrar_vacaciones')
                    ->label('Registrar vacaciones')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Desde')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Hasta')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('fecha_inicio'),
                    ])
                    ->modalDescription('Se cuentan TODOS los días calendario del rango (incluye sábados y domingos), tal como corresponde legalmente.')
                    ->action(function (Employee $record, array $data) {
                        $inicio = \Carbon\Carbon::parse($data['fecha_inicio']);
                        $fin    = \Carbon\Carbon::parse($data['fecha_fin']);

                        $dias = 0;
                        $fecha = $inicio->copy();
                        while ($fecha->lte($fin)) {
                            AttendanceRecord::updateOrCreate(
                                ['employee_id' => $record->id, 'fecha' => $fecha->toDateString()],
                                [
                                    'company_id'            => $record->company_id,
                                    'location_id'           => $record->location_id,
                                    'estado'                => 'vacaciones',
                                    'hora_entrada'          => null,
                                    'hora_salida'           => null,
                                    'minutos_tarde'         => 0,
                                    'minutos_trabajados'    => 0,
                                    'horas_ordinarias'      => 0,
                                    'horas_extra_diurnas'   => 0,
                                    'horas_extra_nocturnas' => 0,
                                    'justificado'           => true,
                                    'corregido_manualmente' => true,
                                    'observacion'           => 'Vacaciones registradas desde Control de Vacaciones ' . now()->format('d/m/Y H:i'),
                                ]
                            );
                            $dias++;
                            $fecha->addDay();
                        }

                        // Informa el saldo resultante, sin bloquear aunque quede negativo.
                        $saldo = app(VacacionesService::class)->calcularSaldo($record->fresh());

                        Notification::make()
                            ->title("Vacaciones registradas: {$dias} días")
                            ->body(isset($saldo['saldo_actual'])
                                ? "Saldo resultante: {$saldo['saldo_actual']} días" . ($saldo['saldo_actual'] < 0 ? ' — ⚠️ QUEDÓ NEGATIVO' : '')
                                : ($saldo['error'] ?? ''))
                            ->color(($saldo['saldo_actual'] ?? 0) < 0 ? 'warning' : 'success')
                            ->send();
                    }),
            ])
            ->defaultSort('apellidos');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListControlVacaciones::route('/'),
        ];
    }
}
