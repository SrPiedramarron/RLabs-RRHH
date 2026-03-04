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

class ReportResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Reportes SUNAFIL';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('generar_reporte')
                    ->label('Generar Reporte SUNAFIL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->form([
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
                                        $user = Auth::user();
                                        return Location::when(
                                            $user->company_id,
                                            fn($q) => $q->where('company_id', $user->company_id)
                                        )->pluck('nombre', 'id');
                                    })
                                    ->searchable()
                                    ->reactive(),

                                Forms\Components\Select::make('department_id')
                                    ->label('Área / Departamento')
                                    ->placeholder('Todos los departamentos')
                                    ->options(function (callable $get) {
                                        $user = Auth::user();
                                        $locationId = $get('location_id');

                                        return Department::when(
                                            $user->company_id,
                                            fn($q) => $q->where('company_id', $user->company_id)
                                        )
                                        ->when($locationId, fn($q) => $q->where('location_id', $locationId))
                                        ->pluck('nombre', 'id');
                                    })
                                    ->searchable(),
                            ]),

                        Forms\Components\Section::make('Opciones del Reporte')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('formato')
                                    ->label('Formato de exportación')
                                    ->options([
                                        'pdf'   => 'PDF',
                                        'excel' => 'Excel (.xlsx)',
                                    ])
                                    ->default('pdf')
                                    ->required(),

                                Forms\Components\Toggle::make('incluye_refrigerio')
                                    ->label('Incluir columnas de refrigerio')
                                    ->helperText('Agrega inicio y fin de refrigerio según Res. 0055-2025')
                                    ->default(false),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $user = Auth::user();

                        $query = AttendanceRecord::with(['employee.department', 'employee.location'])
                            ->whereBetween('fecha', [$data['fecha_inicio'], $data['fecha_fin']])
                            ->when($user->company_id, function ($q) use ($user) {
                                $q->whereHas('employee', fn($e) => $e->where('company_id', $user->company_id));
                            })
                            ->when($data['location_id'] ?? null, function ($q) use ($data) {
                                $q->whereHas('employee', fn($e) => $e->where('location_id', $data['location_id']));
                            })
                            ->when($data['department_id'] ?? null, function ($q) use ($data) {
                                $q->whereHas('employee', fn($e) => $e->where('department_id', $data['department_id']));
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

                        if ($data['formato'] === 'pdf') {
                            return $reportGenerator->generatePdfSunafil($records, $options);
                        } else {
                            return $reportGenerator->generateExcelSunafil($records, $options);
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Empleado')
                    ->searchable(),
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
                    ->label('Tardanza (min)')
                    ->sortable(),
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
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
        ];
    }
}
