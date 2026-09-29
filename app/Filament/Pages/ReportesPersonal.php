<?php

namespace App\Filament\Pages;

use App\Exports\BoletasPendientesExport;
use App\Exports\ContratosPersonalExport;
use App\Exports\CuentasBancariasExport;
use App\Exports\CumpleaniosDelMesExport;
use App\Exports\InformacionPersonalExport;
use App\Exports\PuntualidadPorAreaExport;
use App\Exports\RotacionPersonalExport;
use App\Exports\VacacionesDelMesExport;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PlanillaLiquidacion;
use App\Models\VacacionHistorial;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class ReportesPersonal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Reportes de Personal';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title           = 'Reportes de Personal';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.pages.reportes-personal';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mes'  => now()->month,
            'anio' => now()->year,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtros')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(Company::where('active', true)->pluck('razon_social', 'id'))
                            ->required()
                            ->searchable()
                            ->live(),

                        Forms\Components\Select::make('location_id')
                            ->label('Sede')
                            ->options(fn (Forms\Get $get) => $get('company_id')
                                ? Location::where('company_id', $get('company_id'))->pluck('nombre', 'id')
                                : [])
                            ->placeholder('Todas las sedes'),

                        Forms\Components\Select::make('department_id')
                            ->label('Área')
                            ->options(fn (Forms\Get $get) => $get('company_id')
                                ? Department::where('company_id', $get('company_id'))->pluck('nombre', 'id')
                                : [])
                            ->placeholder('Todas las áreas'),
                    ]),

                Forms\Components\Section::make('Periodo (para vacaciones, boletas, rotación, puntualidad y cumpleaños)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('mes')
                            ->label('Mes')
                            ->options([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                            ])
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('anio')
                            ->label('Año')
                            ->options(array_combine(range(now()->year - 2, now()->year + 1), range(now()->year - 2, now()->year + 1)))
                            ->native(false)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Los 8 reportes se muestran como botones agrupados en el cuerpo de la
     * página (ver reportes-personal.blade.php), no en el header — con 8
     * acciones el header las cortaba fuera de la pantalla sin forma de
     * verlas todas (reportado por RRHH, set. 2026).
     */
    public function gruposDeReportes(): array
    {
        return [
            'Información del Personal' => [
                ['metodo' => 'exportarInformacionPersonal', 'label' => 'Información del Personal', 'icon' => 'heroicon-o-identification', 'color' => 'success'],
                ['metodo' => 'exportarContratos',           'label' => 'Contratos',                 'icon' => 'heroicon-o-document-text', 'color' => 'gray'],
                ['metodo' => 'exportarCumpleanios',         'label' => 'Cumpleaños del Mes',         'icon' => 'heroicon-o-cake',           'color' => 'info'],
                ['metodo' => 'exportarRotacionPersonal',    'label' => 'Altas y Bajas',              'icon' => 'heroicon-o-arrows-right-left', 'color' => 'info'],
            ],
            'Bancario y Planilla' => [
                ['metodo' => 'exportarCuentasBancarias',    'label' => 'Cuentas Bancarias',          'icon' => 'heroicon-o-banknotes',      'color' => 'primary'],
                ['metodo' => 'exportarBoletasPendientes',   'label' => 'Boletas Pendientes de Firma', 'icon' => 'heroicon-o-exclamation-triangle', 'color' => 'danger'],
            ],
            'Asistencia y Vacaciones' => [
                ['metodo' => 'exportarVacacionesDelMes',    'label' => 'Vacaciones del Mes',         'icon' => 'heroicon-o-sun',            'color' => 'warning'],
                ['metodo' => 'exportarPuntualidadPorArea',  'label' => 'Puntualidad por Área',       'icon' => 'heroicon-o-clock',          'color' => 'primary'],
            ],
        ];
    }

    private function empleadosFiltrados()
    {
        $data = $this->form->getState();

        return Employee::with(['department'])
            ->where('company_id', $data['company_id'])
            ->when($data['location_id'] ?? null, fn ($q) => $q->where('location_id', $data['location_id']))
            ->when($data['department_id'] ?? null, fn ($q) => $q->where('department_id', $data['department_id']))
            ->orderBy('apellidos')
            ->get();
    }

    public function exportarCuentasBancarias()
    {
        $empleados = $this->empleadosFiltrados();

        if ($empleados->isEmpty()) {
            $this->sinDatos();
            return;
        }

        return Excel::download(new CuentasBancariasExport($empleados), 'cuentas_bancarias_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportarVacacionesDelMes()
    {
        $data  = $this->form->getState();
        $desde = Carbon::create($data['anio'], $data['mes'], 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $historial = VacacionHistorial::with(['employee.department', 'registradoPor'])
            ->whereBetween('fecha_inicio', [$desde->toDateString(), $hasta->toDateString()])
            ->whereHas('employee', function ($q) use ($data) {
                $q->where('company_id', $data['company_id'])
                    ->when($data['location_id'] ?? null, fn ($qq) => $qq->where('location_id', $data['location_id']))
                    ->when($data['department_id'] ?? null, fn ($qq) => $qq->where('department_id', $data['department_id']));
            })
            ->orderBy('fecha_inicio')
            ->get();

        if ($historial->isEmpty()) {
            $this->sinDatos();
            return;
        }

        $periodoNombre = $desde->locale('es')->isoFormat('MMMM YYYY');

        return Excel::download(new VacacionesDelMesExport($historial, $periodoNombre), 'vacaciones_' . $desde->format('Y-m') . '.xlsx');
    }

    public function exportarInformacionPersonal()
    {
        $empleados = $this->empleadosFiltrados();

        if ($empleados->isEmpty()) {
            $this->sinDatos();
            return;
        }

        return Excel::download(new InformacionPersonalExport($empleados), 'informacion_personal_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportarContratos()
    {
        $empleados = $this->empleadosFiltrados();

        if ($empleados->isEmpty()) {
            $this->sinDatos();
            return;
        }

        return Excel::download(new ContratosPersonalExport($empleados), 'contratos_personal_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportarCumpleanios()
    {
        $data = $this->form->getState();

        $empleados = Employee::with('department')
            ->where('company_id', $data['company_id'])
            ->where('active', true)
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $data['mes'])
            ->when($data['location_id'] ?? null, fn ($q) => $q->where('location_id', $data['location_id']))
            ->when($data['department_id'] ?? null, fn ($q) => $q->where('department_id', $data['department_id']))
            ->get();

        if ($empleados->isEmpty()) {
            $this->sinDatos();
            return;
        }

        $mesNombre = Carbon::create($data['anio'], $data['mes'], 1)->locale('es')->isoFormat('MMMM');

        return Excel::download(new CumpleaniosDelMesExport($empleados, $mesNombre, (int) $data['anio']), 'cumpleanios_' . $data['anio'] . '-' . str_pad($data['mes'], 2, '0', STR_PAD_LEFT) . '.xlsx');
    }

    public function exportarBoletasPendientes()
    {
        $data    = $this->form->getState();
        $periodo = sprintf('%04d-%02d', $data['anio'], $data['mes']);

        $liquidaciones = PlanillaLiquidacion::where('company_id', $data['company_id'])
            ->where('periodo', $periodo)
            ->whereNull('boleta_firmada_path')
            ->when($data['location_id'] ?? null, function ($q) use ($data) {
                $q->whereHas('employee', fn ($e) => $e->where('location_id', $data['location_id']));
            })
            ->when($data['department_id'] ?? null, function ($q) use ($data) {
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $data['department_id']));
            })
            ->orderBy('apellidos')
            ->get();

        if ($liquidaciones->isEmpty()) {
            $this->sinDatos();
            return;
        }

        $mesNombre = Carbon::create($data['anio'], $data['mes'], 1)->locale('es')->isoFormat('MMMM YYYY');

        return Excel::download(new BoletasPendientesExport($liquidaciones, $mesNombre), 'boletas_pendientes_' . $periodo . '.xlsx');
    }

    public function exportarRotacionPersonal()
    {
        $data  = $this->form->getState();
        $desde = Carbon::create($data['anio'], $data['mes'], 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $filtrosComunes = function ($q) use ($data) {
            $q->where('company_id', $data['company_id'])
                ->when($data['location_id'] ?? null, fn ($qq) => $qq->where('location_id', $data['location_id']))
                ->when($data['department_id'] ?? null, fn ($qq) => $qq->where('department_id', $data['department_id']));
        };

        $altas = Employee::with('department')
            ->tap($filtrosComunes)
            ->whereBetween('fecha_ingreso', [$desde->toDateString(), $hasta->toDateString()])
            ->get();

        $bajas = Employee::with('department')
            ->tap($filtrosComunes)
            ->whereNotNull('fecha_cese')
            ->whereBetween('fecha_cese', [$desde->toDateString(), $hasta->toDateString()])
            ->get();

        if ($altas->isEmpty() && $bajas->isEmpty()) {
            $this->sinDatos();
            return;
        }

        $periodoNombre = $desde->locale('es')->isoFormat('MMMM YYYY');

        return Excel::download(new RotacionPersonalExport($altas, $bajas, $periodoNombre), 'rotacion_personal_' . $desde->format('Y-m') . '.xlsx');
    }

    public function exportarPuntualidadPorArea()
    {
        $data  = $this->form->getState();
        $desde = Carbon::create($data['anio'], $data['mes'], 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $records = AttendanceRecord::with('employee.department')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estado', ['presente', 'tarde'])
            ->whereHas('employee', function ($q) use ($data) {
                $q->where('company_id', $data['company_id'])
                    ->when($data['location_id'] ?? null, fn ($qq) => $qq->where('location_id', $data['location_id']))
                    ->when($data['department_id'] ?? null, fn ($qq) => $qq->where('department_id', $data['department_id']));
            })
            ->get();

        if ($records->isEmpty()) {
            $this->sinDatos();
            return;
        }

        $filas = $records
            ->groupBy(fn ($r) => $r->employee->department?->nombre ?? 'Sin área')
            ->map(function ($grupo, $area) {
                return [
                    'area'                => $area,
                    'dias_trabajados'     => $grupo->count(),
                    'dias_tarde'          => $grupo->where('minutos_tarde', '>', 0)->count(),
                    'total_minutos_tarde' => (int) $grupo->sum('minutos_tarde'),
                ];
            })
            ->values();

        $periodoNombre = $desde->locale('es')->isoFormat('MMMM YYYY');

        return Excel::download(new PuntualidadPorAreaExport($filas, $periodoNombre), 'puntualidad_por_area_' . $desde->format('Y-m') . '.xlsx');
    }

    private function sinDatos(): void
    {
        Notification::make()
            ->title('Sin datos')
            ->body('No hay registros para los filtros seleccionados.')
            ->warning()
            ->send();
    }
}
