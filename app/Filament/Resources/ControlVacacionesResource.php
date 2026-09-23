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
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Employee::where('active', true)
                    ->when(\App\Helpers\CompanyContext::get(), fn ($q) => $q->where('company_id', \App\Helpers\CompanyContext::get()))
            )
            ->columns([
                // 1. Trabajador
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Trabajador')
                    ->getStateUsing(fn ($record) => $record->apellidos . ', ' . $record->nombres)
                    ->searchable(['apellidos', 'nombres'])
                    ->sortable(['apellidos']),

                // 2. Fecha de la Última vacación
                Tables\Columns\TextColumn::make('fecha_ultima_vacacion')
                    ->label('Fecha de la Última vacación')
                    ->date('d/m/Y')
                    ->placeholder('Nunca (desde ingreso)')
                    ->sortable(),

                // 3. Días tomados
                Tables\Columns\TextColumn::make('dias_tomados')
                    ->label('Días tomados')
                    ->getStateUsing(fn ($record) => (int) round($record->dias_tomados ?? 0))
                    ->alignCenter()
                    ->sortable(),

                // 4. Saldo de días por tomar
                Tables\Columns\TextColumn::make('saldo_dias')
                    ->label('Saldo de días por tomar')
                    ->getStateUsing(function ($record) {
                        $saldo = app(VacacionesService::class)->calcularSaldo($record);

                        return $saldo['saldo_actual'] ?? '—';
                    })
                    ->badge()
                    ->color(function ($state) {
                        if (! is_numeric($state)) return 'gray';
                        return match (true) {
                            $state < 0  => 'danger',
                            $state == 0 => 'gray',
                            $state < 15 => 'warning',
                            default     => 'success',
                        };
                    })
                    ->alignCenter()
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('registrar_vacaciones')
                    ->label('Registrar vacaciones')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Fecha de inicio')
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Fecha de fin')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('fecha_inicio'),

                        Forms\Components\TextInput::make('observacion')
                            ->label('Observación (opcional)')
                            ->maxLength(255),
                    ])
                    ->action(function (Employee $record, array $data) {
                        $inicio = \Carbon\Carbon::parse($data['fecha_inicio']);
                        $fin    = \Carbon\Carbon::parse($data['fecha_fin']);

                        // Marca cada día del rango como 'vacaciones' en asistencia.
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
                            $fecha->addDay();
                        }

                        // Actualiza fecha_ultima_vacacion (fecha de vuelta) y el contador histórico,
                        // y con eso el saldo vuelve a arrancar en 0 desde la fecha de vuelta.
                        $dias = app(VacacionesService::class)->registrarVacacion($record, $inicio, $fin, $data['observacion'] ?? null);

                        $saldo = app(VacacionesService::class)->calcularSaldo($record->fresh());

                        Notification::make()
                            ->title("Vacaciones registradas: {$dias} días")
                            ->body(isset($saldo['saldo_actual'])
                                ? "Nuevo saldo: {$saldo['saldo_actual']} días, contando desde " . \Carbon\Carbon::parse($saldo['fecha_corte'])->format('d/m/Y')
                                : ($saldo['error'] ?? ''))
                            ->color('success')
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
