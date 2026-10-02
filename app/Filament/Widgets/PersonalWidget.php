<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EmployeeResource;
use App\Helpers\CompanyContext;
use App\Models\Employee;
use App\Models\VacacionHistorial;
use Filament\Widgets\Widget;

class PersonalWidget extends Widget
{
    protected static ?int $sort = -1;
    protected static string $view = 'filament.widgets.personal';
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $companyId = CompanyContext::get();
        $empresa   = fn ($q) => $q->when($companyId, fn ($q2) => $q2->where('company_id', $companyId));

        $hoy      = now()->startOfDay();
        $inicioMes = $hoy->copy()->startOfMonth();

        $activos = Employee::where('active', true)->whereNull('fecha_cese')->tap($empresa)->count();

        $altas = Employee::whereBetween('fecha_ingreso', [$inicioMes->toDateString(), $hoy->toDateString()])
            ->tap($empresa)->count();
        $bajas = Employee::whereBetween('fecha_cese', [$inicioMes->toDateString(), $hoy->toDateString()])
            ->tap($empresa)->count();

        // Cumpleaños de los próximos 7 días (compara mes-día, así cruza fin de año).
        $dias = collect(range(0, 6))->map(fn ($i) => $hoy->copy()->addDays($i));
        $porFecha = $dias->mapWithKeys(fn ($d) => [$d->format('m-d') => $d]);

        $cumples = Employee::where('active', true)->whereNull('fecha_cese')->whereNotNull('fecha_nacimiento')
            ->tap($empresa)->get()
            ->filter(fn ($e) => $porFecha->has($e->fecha_nacimiento->format('m-d')))
            ->map(fn ($e) => [
                'nombre' => $e->nombres . ' ' . $e->apellidos,
                'fecha'  => $porFecha[$e->fecha_nacimiento->format('m-d')],
            ])
            ->sortBy(fn ($c) => $c['fecha']->timestamp)
            ->values();

        // De vacaciones esta semana (cualquier tramo que se cruce con hoy..+6).
        $finSemana = $hoy->copy()->addDays(6);
        $vacaciones = VacacionHistorial::with('employee')
            ->where('fecha_inicio', '<=', $finSemana->toDateString())
            ->where('fecha_fin', '>=', $hoy->toDateString())
            ->whereHas('employee', fn ($q) => $q->when($companyId, fn ($q2) => $q2->where('company_id', $companyId)))
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn ($v) => [
                'nombre' => $v->employee->nombres . ' ' . $v->employee->apellidos,
                'desde'  => $v->fecha_inicio->format('d/m'),
                'hasta'  => $v->fecha_fin->format('d/m'),
                'ahora'  => $v->fecha_inicio->lte($hoy),
            ]);

        return [
            'activos' => $activos,
            'altas'   => $altas,
            'bajas'   => $bajas,
            'cumples' => $cumples,
            'vacaciones' => $vacaciones,
            'urlPersonal' => EmployeeResource::getUrl('index'),
        ];
    }
}
