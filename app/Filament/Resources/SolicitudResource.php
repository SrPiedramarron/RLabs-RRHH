<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitudResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Solicitud;
use App\Services\VacacionesService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Solicitud $record) => $record->estado_color)
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('revisadoPor.name')
                    ->label('Revisada por')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
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
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Solicitud $record) => $record->estado === 'pendiente')
                    ->action(function (Solicitud $record) {
                        static::aprobar($record);
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Solicitud $record) => $record->estado === 'pendiente')
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

                        Notification::make()->title('Solicitud rechazada')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function aprobar(Solicitud $record): void
    {
        if ($record->tipo === 'vacaciones') {
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

        $record->update([
            'estado'       => 'aprobada',
            'revisado_por' => Auth::id(),
            'revisado_at'  => now(),
        ]);

        Notification::make()->title('Solicitud aprobada')->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudes::route('/'),
        ];
    }
}
