<?php

namespace App\Filament\Resources\StatisticsResource\Pages;

use App\Filament\Resources\StatisticsResource;
use App\Models\AttendanceRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CompanyContext;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use App\Support\EstadisticasAsistencia;

class DetallePorEmpleado extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    public function getTitle(): string { return 'Detalle por Empleado'; }

    protected function consultaDetalle(?array $filtroPeriodo): Builder
    {
        [$desde, $hasta] = EstadisticasAsistencia::rango($filtroPeriodo);

        return AttendanceRecord::query()
            ->select([
                'employee_id',
                'company_id',
                DB::raw('SUM(CASE WHEN minutos_tarde > 0 THEN 1 ELSE 0 END) as dias_tarde'),
                DB::raw('SUM(CASE WHEN minutos_tarde <= 0 AND estado NOT IN ("ausente","feriado") THEN 1 ELSE 0 END) as dias_puntual'),
                DB::raw('SUM(CASE WHEN estado = "ausente" THEN 1 ELSE 0 END) as dias_ausente'),
                DB::raw('COUNT(*) as total_dias'),
                DB::raw('SUM(minutos_tarde) as total_minutos'),
            ])
            ->with(['employee', 'employee.department', 'employee.location'])
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
            ->groupBy('employee_id', 'company_id');
    }

    protected function getTableQuery(): Builder
    {
        // El periodo se aplica aquí (no en el filtro de tabla) para que la exportación use el mismo.
        return $this->consultaDetalle($this->tableFilters['periodo'] ?? ['mes' => now()->format('Y-m')]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')->label('Empleado')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.location.nombre')->label('Sede'),
                Tables\Columns\TextColumn::make('employee.department.nombre')->label('Área'),
                Tables\Columns\TextColumn::make('dias_tarde')->label('Días tarde')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('dias_puntual')->label('Días puntual')->badge()->color('success'),
                Tables\Columns\TextColumn::make('dias_ausente')->label('Ausencias')->badge()->color('danger'),
                Tables\Columns\TextColumn::make('total_dias')->label('Total días'),
                Tables\Columns\TextColumn::make('total_minutos')->label('Tiempo tarde (dd:hh:mm)')->formatStateUsing(fn($state) => EstadisticasAsistencia::ddhhmm($state)),
                Tables\Columns\TextColumn::make('pct_puntualidad')->label('% Puntualidad')
                    ->getStateUsing(fn($record) => $record->total_dias ? round(($record->dias_puntual / $record->total_dias) * 100) . '%' : '—')
                    ->badge()
                    ->color(fn($state) => match(true) {
                        $state === '—'    => 'gray',
                        (int)$state >= 90 => 'success',
                        (int)$state >= 70 => 'warning',
                        default           => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('mes')->label('Mes')->options(EstadisticasAsistencia::meses())
                            ->default(now()->format('Y-m'))->placeholder('Todos los meses')->native(false),
                        Forms\Components\DatePicker::make('desde')->label('Desde (opcional, reemplaza al mes)'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta (opcional, reemplaza al mes)'),
                    ])
                    ->query(fn($query) => $query), // se aplica en getTableQuery()
            ])
            ->defaultSort('total_minutos', 'desc');
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
    {
        return (string) $record->employee_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('← Ranking')
                ->url(StatisticsResource::getUrl('index'))
                ->color('gray'),

            Actions\Action::make('ranking_puntualidad')
                ->label('Ranking de puntualidad')
                ->icon('heroicon-o-trophy')
                ->url(StatisticsResource::getUrl('ranking-puntualidad'))
                ->color('gray'),

            Actions\Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = $this->consultaDetalle($this->tableFilters['periodo'] ?? null)->get();

                    return Excel::download(
                        new \App\Exports\DetallePorEmpleadoExport($records),
                        'detalle_empleados_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }
}
