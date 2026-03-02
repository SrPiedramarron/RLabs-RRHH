<?php

namespace App\Filament\Resources\StatisticsResource\Pages;

use App\Filament\Resources\StatisticsResource;
use App\Models\AttendanceRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetallePorEmpleado extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    public function getTitle(): string
    {
        return 'Detalle por Empleado';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('← Ranking')
                ->url(StatisticsResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return AttendanceRecord::query()
            ->select([
                'employee_id',
                DB::raw('SUM(CASE WHEN minutos_tarde > 0 THEN 1 ELSE 0 END) as dias_tarde'),
                DB::raw('SUM(CASE WHEN minutos_tarde <= 0 AND estado NOT IN ("ausente","feriado") THEN 1 ELSE 0 END) as dias_puntual'),
                DB::raw('SUM(CASE WHEN estado = "ausente" THEN 1 ELSE 0 END) as dias_ausente'),
                DB::raw('COUNT(*) as total_dias'),
                DB::raw('SUM(minutos_tarde) as total_minutos'),
            ])
            ->with(['employee.department', 'employee.location'])
            ->when($user->company_id, function ($q) use ($user) {
                $q->whereHas('employee', fn($e) => $e->where('company_id', $user->company_id));
            })
            ->groupBy('employee_id');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('employee.nombre_completo')
                ->label('Empleado')
                ->searchable(),

            Tables\Columns\TextColumn::make('employee.location.nombre')
                ->label('Sede'),

            Tables\Columns\TextColumn::make('employee.department.nombre')
                ->label('Área'),

            Tables\Columns\TextColumn::make('dias_tarde')
                ->label('Días tarde')
                ->badge()
                ->color('warning'),

            Tables\Columns\TextColumn::make('dias_puntual')
                ->label('Días puntual')
                ->badge()
                ->color('success'),

            Tables\Columns\TextColumn::make('dias_ausente')
                ->label('Ausencias')
                ->badge()
                ->color('danger'),

            Tables\Columns\TextColumn::make('total_dias')
                ->label('Total días'),

            Tables\Columns\TextColumn::make('total_minutos')
                ->label('Min. acumulados tarde')
                ->formatStateUsing(fn($state) => $state > 0 ? $state . ' min' : '—'),

            Tables\Columns\TextColumn::make('pct_puntualidad')
                ->label('% Puntualidad')
                ->getStateUsing(function ($record) {
                    if (!$record->total_dias) return '—';
                    $pct = round(($record->dias_puntual / $record->total_dias) * 100);
                    return $pct . '%';
                })
                ->badge()
                ->color(fn($state) => match(true) {
                    $state === '—'     => 'gray',
                    (int)$state >= 90  => 'success',
                    (int)$state >= 70  => 'warning',
                    default            => 'danger',
                }),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
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
                }),
        ];
    }
    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
{
    return (string) $record->employee_id;
}
}
