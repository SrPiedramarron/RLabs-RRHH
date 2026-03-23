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

class DetallePorEmpleado extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    public function getTitle(): string { return 'Detalle por Empleado'; }

    protected function getTableQuery(): Builder
    {
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
            ->groupBy('employee_id', 'company_id');
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
                Tables\Columns\TextColumn::make('total_minutos')->label('Min. tarde')->formatStateUsing(fn($state) => $state > 0 ? $state . ' min' : '—'),
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
                        Forms\Components\DatePicker::make('desde')->label('Desde')->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta')->default(now()->endOfMonth()),
                    ])
                    ->query(fn($query, array $data) => $query
                        ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                        ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']))),
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

            Actions\Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = AttendanceRecord::query()
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
                        ->groupBy('employee_id', 'company_id')
                        ->get();

                    return Excel::download(
                        new \App\Exports\DetallePorEmpleadoExport($records),
                        'detalle_empleados_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }
}
