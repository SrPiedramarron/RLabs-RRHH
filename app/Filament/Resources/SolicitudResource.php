<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitudResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Solicitud;
use App\Services\VacacionesService;
use App\Support\Tabla21Suspension;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SolicitudResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model = Solicitud::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'Solicitudes de Trabajadores';
    protected static ?string $navigationGroup = 'Planilla';
    protected static ?int $navigationSort = 21;
    protected static ?string $modelLabel = 'Solicitud';
    protected static ?string $pluralModelLabel = 'Solicitudes de Trabajadores';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('estado', 'pendiente')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('employee', fn ($q) => $q->where('apellidos', 'like', "%$search%")->orWhere('nombres', 'like', "%$search%")))
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_label')
                    ->label('Tipo')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('periodo')
                    ->label('Periodo / Fecha')
                    ->getStateUsing(function (Solicitud $record) {
                        if ($record->tipo === 'correccion_horas') {
                            $he = $record->hora_entrada_solicitada ? \Carbon\Carbon::parse($record->hora_entrada_solicitada)->format('H:i') : '—';
                            $hs = $record->hora_salida_solicitada ? \Carbon\Carbon::parse($record->hora_salida_solicitada)->format('H:i') : '—';
                            return $record->fecha_registro?->format('d/m/Y') . " ({$he} → {$hs})";
                        }

                        $desde = $record->fecha_inicio?->format('d/m/Y');
                        $hasta = $record->fecha_fin?->format('d/m/Y');

                        return $hasta && $hasta !== $desde ? "{$desde} → {$hasta}" : $desde;
                    }),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('adjunto_path')
                    ->label('Adjunto')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->getStateUsing(fn (Solicitud $record) => (bool) $record->adjunto_path),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Solicitud $record) => $record->estado_color)
                    ->formatStateUsing(fn ($state) => $state === 'pendiente_jefe' ? 'ESPERA AL JEFE' : strtoupper($state))
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('jefe.apellidos')
                    ->label('Jefe directo')
                    ->placeholder('—')
                    ->description(fn (Solicitud $record) => $record->escalada_at ? 'Sin respuesta, pasó a RRHH' : ($record->comentario_jefe ?: null)),

                Tables\Columns\TextColumn::make('revisadoPor.name')
                    ->label('Revisada por')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente (RRHH)',
                        'pendiente_jefe' => 'Espera al jefe',
                        'aprobada'  => 'Aprobada',
                        'rechazada' => 'Rechazada',
                    ])
                    ->default('pendiente'),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'vacaciones'       => 'Vacaciones',
                        'permiso'          => 'Permiso',
                        'correccion_horas' => 'Corrección de horas',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_adjunto')
                    ->label('Ver adjunto')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->url(fn (Solicitud $record) => Storage::disk('public')->url($record->adjunto_path))
                    ->openUrlInNewTab()
                    ->visible(fn (Solicitud $record) => (bool) $record->adjunto_path),

                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Solicitud $record) => in_array($record->estado, ['pendiente', 'pendiente_jefe'], true))
                    ->form(fn (Solicitud $record) => $record->tipo === 'permiso' ? [
                        Forms\Components\Select::make('motivo_suspension_plame')
                            ->label('Código Tabla 21 SUNAT a aplicar')
                            ->options(Tabla21Suspension::OPCIONES)
                            ->required()
                            ->searchable()
                            ->helperText('Elige el código que corresponde al motivo del permiso, para que se declare correcto en PLAME.'),
                    ] : [])
                    ->action(function (Solicitud $record, array $data) {
                        static::aprobar($record, $data['motivo_suspension_plame'] ?? null);
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Solicitud $record) => in_array($record->estado, ['pendiente', 'pendiente_jefe'], true))
                    ->form([
                        Forms\Components\Textarea::make('comentario')
                            ->label('Motivo del rechazo')
                            ->required(),
                    ])
                    ->action(function (Solicitud $record, array $data) {
                        $record->update([
                            'estado'               => 'rechazada',
                            'revisado_por'         => Auth::id(),
                            'revisado_at'          => now(),
                            'comentario_revision'  => $data['comentario'],
                        ]);

                        app(\App\Services\NotificacionSolicitudService::class)->notificarResultado($record->fresh());

                        Notification::make()->title('Solicitud rechazada')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function aprobar(Solicitud $record, ?string $motivoSuspensionPlame = null): void
    {
        match ($record->tipo) {
            'vacaciones'       => static::aprobarVacaciones($record),
            'permiso'          => static::aprobarPermiso($record, $motivoSuspensionPlame),
            'correccion_horas' => static::aprobarCorreccion($record),
        };

        $record->update([
            'estado'       => 'aprobada',
            'revisado_por' => Auth::id(),
            'revisado_at'  => now(),
        ]);

        app(\App\Services\NotificacionSolicitudService::class)->notificarResultado($record->fresh());

        Notification::make()->title('Solicitud aprobada')->success()->send();
    }

    private static function aprobarVacaciones(Solicitud $record): void
    {
        app(VacacionesService::class)->registrarVacacion(
            $record->employee,
            $record->fecha_inicio,
            $record->fecha_fin,
            'Aprobada vía solicitud del trabajador' . ($record->motivo ? " — {$record->motivo}" : '')
        );

        $fecha = $record->fecha_inicio->copy();
        while ($fecha->lte($record->fecha_fin)) {
            \App\Models\AttendanceRecord::updateOrCreate(
                ['employee_id' => $record->employee_id, 'fecha' => $fecha->toDateString()],
                [
                    'company_id'            => $record->company_id,
                    'location_id'           => $record->employee->location_id,
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
                    'observacion'           => 'Vacaciones aprobadas vía solicitud del trabajador ' . now()->format('d/m/Y H:i'),
                ]
            );
            $fecha->addDay();
        }
    }

    private static function aprobarPermiso(Solicitud $record, ?string $motivoSuspensionPlame): void
    {
        $fecha = $record->fecha_inicio->copy();
        while ($fecha->lte($record->fecha_fin)) {
            \App\Models\AttendanceRecord::updateOrCreate(
                ['employee_id' => $record->employee_id, 'fecha' => $fecha->toDateString()],
                [
                    'company_id'              => $record->company_id,
                    'location_id'             => $record->employee->location_id,
                    'estado'                  => 'permiso',
                    'justificado'             => true,
                    'corregido_manualmente'   => true,
                    'motivo_suspension_plame' => $motivoSuspensionPlame,
                    'observacion'             => 'Permiso aprobado vía solicitud del trabajador — ' . $record->motivo,
                ]
            );
            $fecha->addDay();
        }
    }

    private static function aprobarCorreccion(Solicitud $record): void
    {
        $fecha     = $record->fecha_registro->toDateString();
        $existente = \App\Models\AttendanceRecord::where('employee_id', $record->employee_id)
            ->where('fecha', $fecha)
            ->first();

        // La hora corregida reemplaza la que estaba; si la solicitud solo
        // trae una de las dos (entrada o salida), se conserva la otra tal
        // como ya estaba guardada.
        $horaEntradaFinal = $record->hora_entrada_solicitada
            ? $fecha . ' ' . $record->hora_entrada_solicitada
            : $existente?->hora_entrada?->format('Y-m-d H:i:s');

        $horaSalidaFinal = $record->hora_salida_solicitada
            ? $fecha . ' ' . $record->hora_salida_solicitada
            : $existente?->hora_salida?->format('Y-m-d H:i:s');

        // Recalcula tardanza, horas ordinarias, horas extra y estado con la
        // hora YA corregida — si la hora corregida sigue estando fuera de
        // tolerancia, la tardanza se mantiene (correctamente); si ya no lo
        // está, se limpia a 0. Confirmado con RRHH set. 2026: la corrección
        // no debe dejar minutos de tardanza "colgados" de antes.
        $recalculo = app(\App\Services\AttendanceProcessor::class)
            ->recalcularDesdeHoras($record->employee, $fecha, $horaEntradaFinal, $horaSalidaFinal);

        $datos = array_merge($recalculo, [
            'company_id'            => $record->company_id,
            'location_id'           => $record->employee->location_id,
            'corregido_manualmente' => true,
            'motivo_correccion'     => $record->motivo,
            'observacion'           => 'CORREGIDO — vía solicitud del trabajador, aprobada el ' . now()->format('d/m/Y H:i') . '. Motivo: ' . $record->motivo,
        ]);

        if ($record->hora_entrada_solicitada) {
            $datos['hora_entrada']   = $horaEntradaFinal;
            $datos['fuente_entrada'] = 'manual';
        }

        if ($record->hora_salida_solicitada) {
            $datos['hora_salida']   = $horaSalidaFinal;
            $datos['fuente_salida'] = 'manual';
        }

        \App\Models\AttendanceRecord::updateOrCreate(
            ['employee_id' => $record->employee_id, 'fecha' => $fecha],
            $datos
        );

        Notification::make()
            ->title('Corrección aplicada')
            ->body('Tardanza y horas del día se recalcularon con la hora corregida.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudes::route('/'),
        ];
    }
}
