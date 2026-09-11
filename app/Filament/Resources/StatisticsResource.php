<?php

namespace App\Filament\Resources;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Filament\Resources\StatisticsResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CompanyContext;
use Illuminate\Support\Facades\DB;


/**
 * TRES VISTAS EN UN SOLO RESOURCE:
 *   /statistics            → Ranking de tardanzas
 *   /statistics/by-employee → Detalle días tarde vs temprano por trabajador
 *   /statistics/no-checkout → Trabajadores sin marcación de salida
 */
class StatisticsResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Estadísticas Internas';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 2;

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }
    public static function getRecordRouteKeyName(): ?string
    {
        return 'employee_id';
    }
    // ─── Página principal: ranking de tardanzas ────────────────────────────
    public static function table(Table $table): Table
    {

        return $table
            ->query(
                // Agrupa por trabajador y calcula métricas de tardanza
                AttendanceRecord::query()
                    ->select([
                        'employee_id',
                        DB::raw('COUNT(*) as total_dias'),
                        DB::raw('SUM(CASE WHEN minutos_tarde > 0 THEN 1 ELSE 0 END) as dias_tarde'),
                        DB::raw('SUM(CASE WHEN minutos_tarde <= 0 AND estado != "ausente" THEN 1 ELSE 0 END) as dias_puntual'),
                        DB::raw('AVG(CASE WHEN minutos_tarde > 0 THEN minutos_tarde ELSE NULL END) as promedio_tardanza'),
                        DB::raw('MAX(minutos_tarde) as mayor_tardanza'),
                        DB::raw('SUM(minutos_tarde) as total_minutos_tarde'),
                    ])
                    ->with(['employee.department', 'employee.location'])
                    ->when(CompanyContext::get(), function ($q) {
                        $q->whereHas('employee', fn($e) => $e->where('company_id', CompanyContext::get()));
                    })
                    ->groupBy('employee_id')
                    ->orderByDesc('total_minutos_tarde')
            )
            ->heading('Ranking de Tardanzas')
            ->description('Trabajadores ordenados por mayor acumulado de minutos tarde')
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(['employees.nombres', 'employees.apellidos']),

                Tables\Columns\TextColumn::make('employee.location.nombre')
                    ->label('Sede')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.department.nombre')
                    ->label('Área')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_tarde')
                    ->label('Días tarde')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => match(true) {
                        $state >= 10 => 'danger',
                        $state >= 5  => 'warning',
                        default      => 'success',
                    }),

                Tables\Columns\TextColumn::make('dias_puntual')
                    ->label('Días puntual')
                    ->sortable(),

                Tables\Columns\TextColumn::make('promedio_tardanza')
                    ->label('Promedio (min)')
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 1) . ' min' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mayor_tardanza')
                    ->label('Máx. tardanza')
                    ->formatStateUsing(fn($state) => $state ? $state . ' min' : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_minutos_tarde')
                    ->label('Total acumulado')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '—';
                        $h = intdiv($state, 60);
                        $m = $state % 60;
                        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
                    })
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Sede')
                    ->relationship('employee.location', 'nombre'),
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Área')
                    ->relationship('employee.department', 'nombre'),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) $indicators[] = 'Desde: ' . $data['desde'];
                        if ($data['hasta'] ?? null) $indicators[] = 'Hasta: ' . $data['hasta'];
                        return $indicators;
                    }),
            ])
            ->defaultSort('total_minutos_tarde', 'desc')
            ->paginated([15, 30, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index'       => Pages\TardanzaRanking::route('/'),
            'by-employee' => Pages\DetallePorEmpleado::route('/by-employee'),
            'no-checkout' => Pages\SinMarcacionSalida::route('/no-checkout'),
        ];
    }
}