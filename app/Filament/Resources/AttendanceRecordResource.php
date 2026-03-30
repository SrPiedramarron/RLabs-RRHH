<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceRecordResource\Pages;
use App\Models\AttendanceRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Traits\HasCompanyScope;

class AttendanceRecordResource extends Resource
{
    use HasCompanyScope;
    protected static ?string $model = AttendanceRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Asistencia';
    protected static ?string $modelLabel = 'Registro de Asistencia';
    protected static ?string $pluralModelLabel = 'Registros de Asistencia';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del Registro')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Empleado')
                        ->relationship('employee', 'apellidos')
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->nombre_completo)
                        ->required()
                        ->searchable()
                        ->columnSpan(2),

                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->required()
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options([
                            'presente'   => 'Presente',
                            'tarde'      => 'Tarde',
                            'ausente'    => 'Ausente',
                            'feriado'    => 'Feriado',
                            'descanso'   => 'Descanso',
                            'permiso'    => 'Permiso',
                            'vacaciones' => 'Vacaciones',
                        ])
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Marcaciones')
                ->schema([
                    Forms\Components\DateTimePicker::make('hora_entrada')
                        ->label('Hora de Entrada')
                        ->displayFormat('d/m/Y H:i')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('hora_salida')
                        ->label('Hora de Salida')
                        ->displayFormat('d/m/Y H:i')
                        ->nullable(),
                ])->columns(2),

            Forms\Components\Section::make('Cálculos')
                ->schema([
                    Forms\Components\TextInput::make('minutos_tarde')
                        ->label('Minutos de Tardanza')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('horas_ordinarias')
                        ->label('Horas Ordinarias')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('horas_extra_diurnas')
                        ->label('H. Extra Diurnas (25%)')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('horas_extra_nocturnas')
                        ->label('H. Extra Nocturnas (35%)')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('Observaciones')
                ->schema([
                    Forms\Components\Toggle::make('justificado')
                        ->label('Justificado')
                        ->default(false),

                    Forms\Components\Toggle::make('corregido_manualmente')
                        ->label('Corregido Manualmente')
                        ->default(false),

                    Forms\Components\Textarea::make('observacion')
                        ->label('Observación')
                        ->maxLength(500)
                        ->columnSpan(2),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Empleado')
                    ->getStateUsing(fn($record) => $record->employee->nombre_completo)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('employee', fn($q) =>
                            $q->where('apellidos', 'like', "%{$search}%")
                              ->orWhere('nombres', 'like', "%{$search}%")
                        );
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.dni')
                    ->label('DNI'),

                Tables\Columns\TextColumn::make('hora_entrada')
                    ->label('Entrada')
                    ->time('H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('hora_salida')
                    ->label('Salida')
                    ->time('H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('tiempo_tarde')
                    ->label('Tardanza')
                    ->getStateUsing(fn($record) => $record->tiempo_tarde),

                Tables\Columns\TextColumn::make('horas_ordinarias')
                    ->label('H. Ord.')
                    ->numeric(2),

                Tables\Columns\TextColumn::make('total_horas_extra')
                    ->label('H. Extra')
                    ->getStateUsing(fn($record) => $record->total_horas_extra)
                    ->numeric(2),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => 'presente',
                        'warning' => 'tarde',
                        'danger'  => 'ausente',
                        'info'    => fn($state) => in_array($state, ['feriado', 'permiso', 'vacaciones']),
                        'gray'    => 'descanso',
                    ]),

                Tables\Columns\IconColumn::make('justificado')
                    ->label('Just.')
                    ->boolean(),

                Tables\Columns\IconColumn::make('corregido_manualmente')
                    ->label('Corr.')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Sede')
                    ->relationship('location', 'nombre'),

                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'presente'   => 'Presente',
                        'tarde'      => 'Tarde',
                        'ausente'    => 'Ausente',
                        'feriado'    => 'Feriado',
                        'descanso'   => 'Descanso',
                        'permiso'    => 'Permiso',
                        'vacaciones' => 'Vacaciones',
                    ]),

                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAttendanceRecords::route('/'),
            'create' => Pages\CreateAttendanceRecord::route('/create'),
            'edit'   => Pages\EditAttendanceRecord::route('/{record}/edit'),
        ];
    }
}