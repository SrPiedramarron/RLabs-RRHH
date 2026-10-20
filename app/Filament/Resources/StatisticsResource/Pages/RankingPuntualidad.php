<?php

namespace App\Filament\Resources\StatisticsResource\Pages;

use App\Filament\Resources\StatisticsResource;
use App\Helpers\CompanyContext;
use App\Models\AttendanceRecord;
use App\Support\EstadisticasAsistencia;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ranking porcentual de puntualidad: general (trabajadores), por sede, por área
 * y por área dentro de cada sede. % = días puntuales / total de días registrados
 * (misma definición que el detalle por empleado), acumulado — no promedio de promedios.
 */
class RankingPuntualidad extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    public function getTitle(): string { return 'Ranking de puntualidad'; }

    public function getTabs(): array
    {
        return [
            'general'   => Tab::make('General (trabajadores)'),
            'sedes'     => Tab::make('Por sede'),
            'areas'     => Tab::make('Por área'),
            'sede_area' => Tab::make('Áreas por sede'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'general';
    }

    private function vista(): string
    {
        return (string) ($this->activeTab ?: 'general');
    }

    protected function getTableQuery(): Builder
    {
        return $this->consulta($this->vista());
    }

    protected function consulta(string $vista): Builder
    {
        [$desde, $hasta] = EstadisticasAsistencia::rango($this->periodoDe($vista));

        $sub = DB::table('attendance_records as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->when(CompanyContext::get(), fn ($q) => $q->where('a.company_id', CompanyContext::get()))
            ->when($desde, fn ($q) => $q->whereDate('a.fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('a.fecha', '<=', $hasta));

        $grupo = match ($vista) {
            'sedes'     => ['e.location_id'],
            'areas'     => ['e.department_id'],
            'sede_area' => ['e.location_id', 'e.department_id'],
            default     => ['e.id'],
        };
        $sub->groupBy($grupo);

        $sub->selectRaw("CONCAT_WS('-', " . implode(', ', array_map(fn ($g) => "IFNULL($g, 0)", $grupo)) . ') as clave')
            ->selectRaw("MAX(CONCAT(e.apellidos, ', ', e.nombres)) as empleado")
            ->selectRaw("MAX(COALESCE(l.nombre, 'Sin sede')) as sede")
            ->selectRaw("MAX(COALESCE(d.nombre, 'Sin área')) as area")
            ->selectRaw('COUNT(DISTINCT e.id) as n_empleados')
            ->selectRaw('SUM(CASE WHEN a.minutos_tarde > 0 THEN 1 ELSE 0 END) as dias_tarde')
            ->selectRaw('SUM(CASE WHEN a.minutos_tarde <= 0 AND a.estado NOT IN ("ausente","feriado") THEN 1 ELSE 0 END) as dias_puntual')
            ->selectRaw('SUM(CASE WHEN a.estado = "ausente" THEN 1 ELSE 0 END) as dias_ausente')
            ->selectRaw('COUNT(*) as total_dias')
            ->selectRaw('SUM(a.minutos_tarde) as total_minutos')
            ->selectRaw('ROUND(100 * SUM(CASE WHEN a.minutos_tarde <= 0 AND a.estado NOT IN ("ausente","feriado") THEN 1 ELSE 0 END) / COUNT(*), 1) as pct');

        // Posición: general/sede/área en un solo ranking; áreas por sede reinicia en cada sede.
        $particion = $vista === 'sede_area' ? 'PARTITION BY r.sede ' : '';
        $query = AttendanceRecord::withoutGlobalScopes()
            ->fromSub($sub, 'r')
            ->select('r.*')
            ->selectRaw("RANK() OVER ({$particion}ORDER BY r.pct DESC) as posicion");

        return $vista === 'sede_area'
            ? $query->orderBy('r.sede')->orderBy('r.pct', 'desc')
            : $query->orderBy('r.pct', 'desc');
    }

    /** Cada ranking (pestaña) tiene su propio periodo: campos mes_<vista>, desde_<vista>, hasta_<vista>. */
    private function periodoDe(string $vista): array
    {
        $f = $this->tableFilters['periodo'] ?? [];

        return [
            'mes'   => array_key_exists("mes_$vista", $f) ? $f["mes_$vista"] : now()->format('Y-m'),
            'desde' => $f["desde_$vista"] ?? null,
            'hasta' => $f["hasta_$vista"] ?? null,
        ];
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->clave;
    }

    public function table(Table $table): Table
    {
        $en = fn (array $vistas) => fn () => in_array($this->vista(), $vistas, true);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('posicion')->label('#')->alignCenter(),
                Tables\Columns\TextColumn::make('empleado')->label('Trabajador')->visible($en(['general'])),
                Tables\Columns\TextColumn::make('sede')->label('Sede')->visible($en(['general', 'sedes', 'sede_area'])),
                Tables\Columns\TextColumn::make('area')->label('Área')->visible($en(['general', 'areas', 'sede_area'])),
                Tables\Columns\TextColumn::make('n_empleados')->label('Trabajadores')->alignCenter()->visible($en(['sedes', 'areas', 'sede_area'])),
                Tables\Columns\TextColumn::make('dias_tarde')->label('Días tarde')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('dias_puntual')->label('Días puntual')->badge()->color('success'),
                Tables\Columns\TextColumn::make('dias_ausente')->label('Ausencias')->badge()->color('danger'),
                Tables\Columns\TextColumn::make('total_dias')->label('Total días'),
                Tables\Columns\TextColumn::make('total_minutos')->label('Tiempo tarde (dd:hh:mm)')
                    ->formatStateUsing(fn ($state) => EstadisticasAsistencia::ddhhmm($state)),
                Tables\Columns\TextColumn::make('pct')->label('% Puntualidad')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1) . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (float) $state >= 90 => 'success',
                        (float) $state >= 70 => 'warning',
                        default              => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->form(collect(['general', 'sedes', 'areas', 'sede_area'])->flatMap(fn ($v) => [
                        Forms\Components\Select::make("mes_$v")->label('Mes')->options(EstadisticasAsistencia::meses())
                            ->default(now()->format('Y-m'))->placeholder('Todos los meses')->native(false)
                            ->visible(fn () => $this->vista() === $v),
                        Forms\Components\DatePicker::make("desde_$v")->label('Desde (opcional, reemplaza al mes)')
                            ->visible(fn () => $this->vista() === $v),
                        Forms\Components\DatePicker::make("hasta_$v")->label('Hasta (opcional, reemplaza al mes)')
                            ->visible(fn () => $this->vista() === $v),
                    ])->all())
                    ->query(fn ($query) => $query), // se aplica en getTableQuery()
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_este')
                ->label('Exportar este ranking')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $v = $this->vista();

                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\RankingPuntualidadExport([$v => $this->consulta($v)->get()]),
                        'ranking_puntualidad_' . $v . '_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),

            Actions\Action::make('exportar')
                ->label('Exportar Excel (todos los rankings)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $porVista = [];
                    foreach (['general', 'sedes', 'areas', 'sede_area'] as $v) {
                        $porVista[$v] = $this->consulta($v)->get();
                    }

                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\RankingPuntualidadExport($porVista),
                        'ranking_puntualidad_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),

            Actions\Action::make('volver')
                ->label('← Detalle por empleado')
                ->url(StatisticsResource::getUrl('by-employee'))
                ->color('gray'),
        ];
    }
}
