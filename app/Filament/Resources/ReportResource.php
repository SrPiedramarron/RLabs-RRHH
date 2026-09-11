<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Services\ReportGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CompanyContext;
use App\Filament\Traits\HasCompanyScope;

class ReportResource extends Resource
{
    use HasCompanyScope;
    
    protected static ?string $model = AttendanceRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Reportes SUNAFIL';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $modelLabel = 'Reporte SUNAFIL';
    protected static ?string $pluralModelLabel = 'Reportes SUNAFIL';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Asistencia Manual')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Registrar Asistencia Manual')
                    ->form([
                        Forms\Components\Section::make('Trabajador y Fecha')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('employee_id')
                                    ->label('Trabajador')
                                    ->options(fn() =>
                                        \App\Models\Employee::where('active', true)
                                            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
                                            ->get()->pluck('nombre_completo', 'id')
                                    )
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $emp = \App\Models\Employee::find($state);
                                            if ($emp) {
                                                $set('company_id',  $emp->company_id);
                                                $set('location_id', $emp->location_id);
                                            }
                                        }
                                    }),
                                Forms\Components\DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->required(),
                                Forms\Components\Hidden::make('company_id'),
                                Forms\Components\Hidden::make('location_id'),
                            ]),
                        Forms\Components\Section::make('Marcaciones')
                            ->columns(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('hora_entrada')
                                    ->label('Hora Entrada')
                                    ->seconds(false),
                                Forms\Components\DateTimePicker::make('hora_salida')
                                    ->label('Hora Salida')
                                    ->seconds(false),
                                Forms\Components\Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'presente'   => 'Presente',
                                        'tarde'      => 'Tarde',
                                        'descanso'   => 'Descanso',
                                        'feriado'    => 'Feriado',
                                        'permiso'    => 'Permiso',
                                        'vacaciones' => 'Vacaciones',
                                    ])
                                    ->default('presente')
                                    ->required(),
                                Forms\Components\TextInput::make('motivo_correccion')
                                    ->label('Motivo')
                                    ->required()
                                    ->placeholder('Ej: Trabajo en feriado, recuperación de horas')
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('observacion')
                                    ->label('Observación')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['corregido_manualmente'] = 1;
                        if (!empty($data['hora_entrada']) && !empty($data['hora_salida'])) {
                            $entrada = \Carbon\Carbon::parse($data['hora_entrada']);
                            $salida  = \Carbon\Carbon::parse($data['hora_salida']);
                            $minutos = (int) $entrada->diffInMinutes($salida, true);
                            $data['minutos_trabajados'] = $minutos;
                            $data['horas_ordinarias']   = round($minutos / 60, 2);
                        }
                        return $data;
                    })
                    ->successNotificationTitle('Asistencia registrada correctamente'),

                Tables\Actions\Action::make('generar_reporte')
                    ->label('Generar Reporte SUNAFIL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->modalSubmitActionLabel('Generar Reporte')
                    ->form([
                        Forms\Components\Section::make('Tipo de Reporte')
                            ->schema([
                                Forms\Components\Radio::make('tipo')
                                    ->label('')
                                    ->options([
                                        'general'    => 'Reporte General (todos los trabajadores)',
                                        'individual' => 'Reporte Individual (por trabajador)',
                                    ])
                                    ->default('general')
                                    ->live()
                                    ->inline(),
                            ]),

                        Forms\Components\Section::make('Período')
                            ->columns(2)
                            ->schema([
                                Forms\Components\DatePicker::make('fecha_inicio')
                                    ->label('Desde')
                                    ->required()
                                    ->default(now()->startOfMonth()),
                                Forms\Components\DatePicker::make('fecha_fin')
                                    ->label('Hasta')
                                    ->required()
                                    ->default(now()->endOfMonth()),
                            ]),

                        Forms\Components\Section::make('Filtros')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('location_id')
                                    ->label('Sede')
                                    ->placeholder('Todas las sedes')
                                    ->options(function () {
                                        return Location::when(CompanyContext::get(),
                                            fn($q) => $q->where('company_id', CompanyContext::get())
                                        )->pluck('nombre', 'id');
                                    })
                                    ->searchable()
                                    ->reactive(),

                                Forms\Components\Select::make('employee_id')
                                    ->label('Trabajador')
                                    ->options(fn() =>
                                        Employee::where('active', true)
                                            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
                                            ->get()
                                            ->pluck('nombre_completo', 'id')
                                    )
                                    ->searchable()
                                    ->required(fn(Forms\Get $get) => $get('tipo') === 'individual')
                                    ->visible(fn(Forms\Get $get) => $get('tipo') === 'individual')
                                    ->placeholder('Selecciona un trabajador')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('department_id')
                                    ->label('Empresa')
                                    ->placeholder('Todas las empresas')
                                    ->options(function (callable $get) {
                                        $locationId = $get('location_id');

                                        return Department::when(CompanyContext::get(),
                                            fn($q) => $q->where('company_id', CompanyContext::get())
                                        )
                                        
                                        ->pluck('nombre', 'id');
                                    })
                                    ->searchable(),
                            ]),

                        Forms\Components\Section::make('Opciones del Reporte')
                            ->columns(2)
                            ->schema([

                                Forms\Components\Toggle::make('incluye_refrigerio')
                                    ->label('Incluir columnas de refrigerio')
                                    ->helperText('Agrega inicio y fin de refrigerio según Res. 0055-2025')
                                    ->default(false),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $query = AttendanceRecord::with(['employee.department', 'employee.location'])
                            ->whereBetween('fecha', [$data['fecha_inicio'], $data['fecha_fin']])
                            ->when(CompanyContext::get(), function ($q) {
                                $q->whereHas('employee', fn($e) => $e->where('company_id', CompanyContext::get()));
                            })
                            ->when($data['location_id'] ?? null, function ($q) use ($data) {
                                $q->whereHas('employee', fn($e) => $e->where('location_id', $data['location_id']));
                            })
                            ->when($data['department_id'] ?? null, function ($q) use ($data) {
                                $q->whereHas('employee', fn($e) => $e->where('department_id', $data['department_id']));
                            })
                            ->when(($data['tipo'] ?? 'general') === 'individual' && !empty($data['employee_id']), function ($q) use ($data) {
                                $q->where('employee_id', $data['employee_id']);
                            })
                            ->orderBy('fecha')
                            ->orderBy('employee_id');

                        $records = $query->get();

                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Sin datos')
                                ->body('No hay registros para los filtros seleccionados.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $reportGenerator = app(ReportGenerator::class);

                        $options = [
                            'fecha_inicio'      => $data['fecha_inicio'],
                            'fecha_fin'         => $data['fecha_fin'],
                            'incluye_refrigerio' => $data['incluye_refrigerio'] ?? false,
                            'location_id'       => $data['location_id'] ?? null,
                            'department_id'     => $data['department_id'] ?? null,
                        ];

                        // Siempre Excel
                        if (false) {
                            return $reportGenerator->generatePdfSunafil($records, $options);
                        } else {
                            return $reportGenerator->generateExcelSunafil($records, $options);
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(['employees.apellidos', 'employees.nombres']),
                Tables\Columns\TextColumn::make('employee.location.nombre')
                    ->label('Sede')
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.department.nombre')
                    ->label('Área')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hora_entrada')
                    ->label('Entrada'),
                Tables\Columns\TextColumn::make('hora_salida')
                    ->label('Salida')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => 'puntual',
                        'warning' => 'tardanza',
                        'danger'  => 'ausente',
                        'gray'    => 'feriado',
                    ]),
                Tables\Columns\TextColumn::make('minutos_tarde')
                    ->label('Tardanza')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0
                        ? sprintf('%02d:%02d', intdiv($state, 60), $state % 60)
                        : '—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Sede')
                    ->relationship('employee.location', 'nombre'),
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Área')
                    ->relationship('employee.department', 'nombre'),
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('hasta')->default(now()->endOfMonth()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Corregir')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Corrección Manual de Asistencia')
                    ->form([
                        Forms\Components\Section::make('Datos del Registro')
                            ->columns(2)
                            ->schema([
                                Forms\Components\DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'presente'   => 'Presente',
                                        'tarde'      => 'Tarde',
                                        'ausente'    => 'Ausente',
                                        'descanso'   => 'Descanso',
                                        'feriado'    => 'Feriado',
                                        'permiso'    => 'Permiso',
                                        'vacaciones' => 'Vacaciones',
                                    ])
                                    ->required(),
                                Forms\Components\DateTimePicker::make('hora_entrada')
                                    ->label('Hora Entrada')
                                    ->seconds(false),
                                Forms\Components\DateTimePicker::make('hora_salida')
                                    ->label('Hora Salida')
                                    ->seconds(false),
                                Forms\Components\TimePicker::make('inicio_refrigerio')
                                    ->label('Inicio Refrigerio')
                                    ->seconds(false),
                                Forms\Components\TimePicker::make('fin_refrigerio')
                                    ->label('Fin Refrigerio')
                                    ->seconds(false),
                            ]),
                        Forms\Components\Section::make('Corrección')
                            ->schema([
                                Forms\Components\Textarea::make('observacion')
                                    ->label('Observación')
                                    ->rows(2),
                                Forms\Components\TextInput::make('motivo_correccion')
                                    ->label('Motivo de Corrección')
                                    ->required()
                                    ->placeholder('Ej: Error en reloj, trabajo en feriado, etc.'),
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['corregido_manualmente'] = 1;
                        if (!empty($data['hora_entrada']) && !empty($data['hora_salida'])) {
                            $entrada = \Carbon\Carbon::parse($data['hora_entrada']);
                            $salida  = \Carbon\Carbon::parse($data['hora_salida']);
                            $minutos = (int) $entrada->diffInMinutes($salida, true);
                            $data['minutos_trabajados'] = $minutos;
                            $data['horas_ordinarias']   = round($minutos / 60, 2);
                        }
                        return $data;
                    })
                    ->successNotificationTitle('Registro corregido correctamente'),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
        ];
    }
}
