<?php

namespace App\Filament\Pages;

use App\Exports\ContratosPersonalExport;
use App\Exports\CuentasBancariasExport;
use App\Exports\InformacionPersonalExport;
use App\Exports\VacacionesDelMesExport;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\VacacionHistorial;
use Carbon\Carbon;
use Filament\Actions\Action;
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

                Forms\Components\Section::make('Periodo (solo para el reporte de vacaciones)')
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cuentas_bancarias')
                ->label('Cuentas Bancarias')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->action('exportarCuentasBancarias'),

            Action::make('vacaciones_mes')
                ->label('Vacaciones del Mes')
                ->icon('heroicon-o-sun')
                ->color('warning')
                ->action('exportarVacacionesDelMes'),

            Action::make('informacion_personal')
                ->label('Información del Personal')
                ->icon('heroicon-o-identification')
                ->color('success')
                ->action('exportarInformacionPersonal'),

            Action::make('contratos')
                ->label('Contratos')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action('exportarContratos'),
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

    private function sinDatos(): void
    {
        Notification::make()
            ->title('Sin datos')
            ->body('No hay registros para los filtros seleccionados.')
            ->warning()
            ->send();
    }
}
