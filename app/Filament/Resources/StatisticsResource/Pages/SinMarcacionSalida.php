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

class SinMarcacionSalida extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    // Sobreescribe el query base para mostrar solo registros sin hora_salida
    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return AttendanceRecord::query()
            ->whereNotNull('hora_entrada')
            ->whereNull('hora_salida')
            ->where('estado', '!=', 'ausente')
            ->with(['employee.department', 'employee.location'])
            ->when($user->company_id, function ($q) use ($user) {
                $q->whereHas('employee', fn($e) => $e->where('company_id', $user->company_id));
            });
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

            Tables\Columns\TextColumn::make('fecha')
                ->label('Fecha')
                ->date('d/m/Y')
                ->sortable(),

            Tables\Columns\TextColumn::make('hora_entrada')
                ->label('Hora entrada'),

            Tables\Columns\BadgeColumn::make('marcacion_salida')
                ->label('Marcó salida')
                ->getStateUsing(fn($record) => $record->hora_salida ? 'Sí' : 'No')
                ->colors([
                    'success' => 'Sí',
                    'danger'  => 'No',
                ]),
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
                        ->default(now()->subDays(30)),
                    Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta')
                        ->default(now()),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                        ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']));
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('← Ranking')
                ->url(StatisticsResource::getUrl('index'))
                ->color('gray'),

            // Resumen: cuántos empleados distintos tienen días sin marcar salida
            Actions\Action::make('resumen')
                ->label(fn() => $this->getTableQuery()->distinct('employee_id')->count('employee_id') . ' empleados afectados')
                ->disabled()
                ->color('warning'),
        ];
    }

    public function getTitle(): string
    {
        return 'Empleados sin Marcación de Salida';
    }
}
