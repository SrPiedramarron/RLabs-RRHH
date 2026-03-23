<?php

namespace App\Filament\Pages;

use App\Exports\AttendanceExport;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class GenerarReporte extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Reportes SUNAFIL';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title           = 'Generar Reportes SUNAFIL';
    protected static ?int    $navigationSort  = 8;
    protected static string  $view            = 'filament.pages.generar-reporte';

    public ?array $data = [];

    // Controla si se muestra la vista previa y qué tipo es
    public bool   $mostrarPrevia  = false;
    public string $tipoPrevia     = ''; // 'pdf' | 'excel'
    public string $htmlPrevia     = '';
    public array  $excelData      = [];
    public array  $excelHeaders   = [];

    public function mount(): void
    {
        $this->form->fill([
            'desde' => now()->startOfMonth()->format('Y-m-d'),
            'hasta' => now()->format('Y-m-d'),
            'tipo'  => 'general',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tipo de Reporte')
                    ->schema([
                        Forms\Components\Radio::make('tipo')
                            ->label('')
                            ->options([
                                'general'    => 'Reporte General (todos los empleados)',
                                'individual' => 'Reporte Individual (por empleado)',
                            ])
                            ->default('general')
                            ->live()
                            ->inline(),
                    ]),

                Forms\Components\Section::make('Parámetros')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(Company::where('active', true)->pluck('razon_social', 'id'))
                            ->required()
                            ->searchable()
                            ->live(),

                        Forms\Components\Select::make('location_id')
                            ->label('Sede')
                            ->options(Location::where('active', true)->pluck('nombre', 'id'))
                            ->placeholder('Todas las sedes')
                            ->nullable(),

                        Forms\Components\Select::make('employee_id')
                            ->label('Empleado')
                            ->options(fn(Forms\Get $get) =>
                                $get('company_id')
                                    ? Employee::where('company_id', $get('company_id'))
                                        ->where('active', true)
                                        ->get()
                                        ->pluck('nombre_completo', 'id')
                                    : []
                            )
                            ->searchable()
                            ->required(fn(Forms\Get $get) => $get('tipo') === 'individual')
                            ->visible(fn(Forms\Get $get) => $get('tipo') === 'individual')
                            ->placeholder('Selecciona un empleado'),

                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Toggle::make('incluir_refrigerio')
                            ->label('Incluir columna de refrigerio')
                            ->default(false)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── VISTA PREVIA PDF ──────────────────────────────────────────
            Action::make('previa_pdf')
                ->label('Vista Previa PDF')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action('mostrarPreviaPDF'),

            // ── DESCARGAR PDF ─────────────────────────────────────────────
            Action::make('exportar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document')
                ->color('danger')
                ->action('exportarPDF'),

            // ── VISTA PREVIA EXCEL ────────────────────────────────────────
            Action::make('previa_excel')
                ->label('Vista Previa Excel')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action('mostrarPreviaExcel')
                ->visible(fn() => ($this->data['tipo'] ?? 'general') === 'general'),

            // ── DESCARGAR EXCEL ───────────────────────────────────────────
            Action::make('exportar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action('exportarExcel')
                ->visible(fn() => ($this->data['tipo'] ?? 'general') === 'general'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VISTA PREVIA PDF
    // ─────────────────────────────────────────────────────────────────────────

    public function mostrarPreviaPDF(): void
    {
        $data = $this->form->getState();

        if (($data['tipo'] ?? 'general') === 'individual') {
            $this->htmlPrevia = $this->buildHtmlIndividual($data);
        } else {
            $this->htmlPrevia = $this->buildHtmlGeneral($data);
        }

        $this->tipoPrevia    = 'pdf';
        $this->mostrarPrevia = true;
    }

    public function cerrarPrevia(): void
    {
        $this->mostrarPrevia = false;
        $this->htmlPrevia    = '';
        $this->excelData     = [];
        $this->excelHeaders  = [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VISTA PREVIA EXCEL (tabla HTML)
    // ─────────────────────────────────────────────────────────────────────────

    public function mostrarPreviaExcel(): void
    {
        $data = $this->form->getState();

        $records = AttendanceRecord::with(['employee', 'location'])
            ->where('company_id', $data['company_id'])
            ->when($data['location_id'] ?? null, fn($q) => $q->where('location_id', $data['location_id']))
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->whereHas('employee', fn($q) => $q->where('exonerado_registro', false))
            ->orderBy('fecha')
            ->orderBy('employee_id')
            ->get();

        $this->excelHeaders = [
            'Fecha', 'Apellidos y Nombres', 'DNI', 'Cargo', 'Sede',
            'H. Entrada', 'H. Salida', 'Tardanza (min)',
            'H. Ordinarias', 'H. Extra Diurna', 'H. Extra Noct.', 'Estado',
        ];

        $this->excelData = $records->map(fn($r) => [
            $r->fecha->format('d/m/Y'),
            $r->employee->nombre_completo,
            $r->employee->dni,
            $r->employee->cargo ?? '—',
            $r->location->nombre ?? '—',
            $r->hora_entrada?->format('H:i') ?? '—',
            $r->hora_salida?->format('H:i')  ?? '—',
            $r->minutos_tarde > 0 ? $r->minutos_tarde : '—',
            $r->horas_ordinarias  > 0 ? sprintf('%02d:%02d', intdiv((int)round($r->horas_ordinarias * 60), 60), (int)round($r->horas_ordinarias * 60) % 60) : '—',
            $r->horas_extra_diurnas   > 0 ? sprintf('%02d:%02d', intdiv((int)round($r->horas_extra_diurnas * 60), 60), (int)round($r->horas_extra_diurnas * 60) % 60) : '—',
            $r->horas_extra_nocturnas > 0 ? sprintf('%02d:%02d', intdiv((int)round($r->horas_extra_nocturnas * 60), 60), (int)round($r->horas_extra_nocturnas * 60) % 60) : '—',
            strtoupper($r->estado),
        ])->toArray();

        $this->tipoPrevia    = 'excel';
        $this->mostrarPrevia = true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS — construyen el HTML del reporte (reutilizan los mismos blades)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildHtmlGeneral(array $data): string
    {
        $company  = Company::findOrFail($data['company_id']);
        $location = isset($data['location_id']) ? Location::find($data['location_id']) : null;

        $records = AttendanceRecord::with(['employee', 'location'])
            ->where('company_id', $data['company_id'])
            ->when($data['location_id'] ?? null, fn($q) => $q->where('location_id', $data['location_id']))
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->whereHas('employee', fn($q) => $q->where('exonerado_registro', false))
            ->orderBy('fecha')
            ->orderBy('employee_id')
            ->get();

        $totales = [
            'presentes'       => $records->where('estado', 'presente')->count(),
            'tardanzas'       => $records->where('estado', 'tarde')->count(),
            'ausentes'        => $records->where('estado', 'ausente')->count(),
            'horas_extra'     => round($records->sum('horas_extra_diurnas') + $records->sum('horas_extra_nocturnas'), 2),
            'total_empleados' => $records->pluck('employee_id')->unique()->count(),
        ];

        return view('reports.attendance_sunafil', array_merge(
            compact('records', 'company', 'location', 'totales'),
            ['desde' => $data['desde'], 'hasta' => $data['hasta']]
        ))->render();
    }

    private function buildHtmlIndividual(array $data): string
    {
        $company  = Company::findOrFail($data['company_id']);
        $employee = Employee::with(['location', 'schedule', 'department'])
            ->findOrFail($data['employee_id']);

        $records = AttendanceRecord::with(['employee', 'location'])
            ->where('employee_id', $data['employee_id'])
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->orderBy('fecha')
            ->get();

        $totales = [
            'dias_laborables'       => $records->whereNotIn('estado', ['descanso', 'feriado'])->count(),
            'presentes'             => $records->where('estado', 'presente')->count(),
            'tardanzas'             => $records->where('estado', 'tarde')->count(),
            'ausentes'              => $records->where('estado', 'ausente')->count(),
            'minutos_tarde'         => $records->sum('minutos_tarde'),
            'horas_ordinarias'      => round($records->sum('horas_ordinarias'), 2),
            'horas_extra_diurnas'   => round($records->sum('horas_extra_diurnas'), 2),
            'horas_extra_nocturnas' => round($records->sum('horas_extra_nocturnas'), 2),
        ];

        return view('reports.employee_monthly', array_merge(
            compact('records', 'company', 'employee', 'totales'),
            ['desde' => $data['desde'], 'hasta' => $data['hasta']]
        ))->render();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESCARGAS (sin cambios respecto al original)
    // ─────────────────────────────────────────────────────────────────────────

    public function exportarPDF(): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->form->getState();

        if (($data['tipo'] ?? 'general') === 'individual') {
            return $this->exportarPDFIndividual($data);
        }

        return $this->exportarPDFGeneral($data);
    }

    private function exportarPDFGeneral(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $company  = Company::findOrFail($data['company_id']);
        $location = isset($data['location_id']) ? Location::find($data['location_id']) : null;

        $records = AttendanceRecord::with(['employee', 'location'])
            ->where('company_id', $data['company_id'])
            ->when($data['location_id'] ?? null, fn($q) => $q->where('location_id', $data['location_id']))
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->whereHas('employee', fn($q) => $q->where('exonerado_registro', false))
            ->orderBy('fecha')
            ->orderBy('employee_id')
            ->get();

        $totales = [
            'presentes'       => $records->where('estado', 'presente')->count(),
            'tardanzas'       => $records->where('estado', 'tarde')->count(),
            'ausentes'        => $records->where('estado', 'ausente')->count(),
            'horas_extra'     => round($records->sum('horas_extra_diurnas') + $records->sum('horas_extra_nocturnas'), 2),
            'total_empleados' => $records->pluck('employee_id')->unique()->count(),
        ];

        $pdf = Pdf::loadView('reports.attendance_sunafil', compact(
            'records', 'company', 'location', 'totales'
        ) + ['desde' => $data['desde'], 'hasta' => $data['hasta']])
            ->setPaper('a4', 'landscape');

        $filename = 'registro_asistencia_' . $data['desde'] . '_' . $data['hasta'] . '.pdf';

        return response()->streamDownload(
            fn() => print($pdf->output()),
            $filename
        );
    }

    private function exportarPDFIndividual(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $company  = Company::findOrFail($data['company_id']);
        $employee = Employee::with(['location', 'schedule', 'department'])
            ->findOrFail($data['employee_id']);

        $records = AttendanceRecord::with(['employee', 'location'])
            ->where('employee_id', $data['employee_id'])
            ->whereBetween('fecha', [$data['desde'], $data['hasta']])
            ->orderBy('fecha')
            ->get();

        $totales = [
            'dias_laborables'       => $records->whereNotIn('estado', ['descanso', 'feriado'])->count(),
            'presentes'             => $records->where('estado', 'presente')->count(),
            'tardanzas'             => $records->where('estado', 'tarde')->count(),
            'ausentes'              => $records->where('estado', 'ausente')->count(),
            'minutos_tarde'         => $records->sum('minutos_tarde'),
            'horas_ordinarias'      => round($records->sum('horas_ordinarias'), 2),
            'horas_extra_diurnas'   => round($records->sum('horas_extra_diurnas'), 2),
            'horas_extra_nocturnas' => round($records->sum('horas_extra_nocturnas'), 2),
        ];

        $pdf = Pdf::loadView('reports.employee_monthly', compact(
            'records', 'company', 'employee', 'totales'
        ) + ['desde' => $data['desde'], 'hasta' => $data['hasta']])
            ->setPaper('a4', 'portrait');

        $filename = 'asistencia_' . str_replace(' ', '_', $employee->apellidos) . '_' . $data['desde'] . '_' . $data['hasta'] . '.pdf';

        return response()->streamDownload(
            fn() => print($pdf->output()),
            $filename
        );
    }

    public function exportarExcel(): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->form->getState();

        $filename = 'registro_asistencia_' . $data['desde'] . '_' . $data['hasta'] . '.xlsx';

        return Excel::download(
            new AttendanceExport(
                companyId:          $data['company_id'],
                locationId:         $data['location_id'] ?? null,
                desde:              $data['desde'],
                hasta:              $data['hasta'],
                incluirRefrigerio:  $data['incluir_refrigerio'] ?? false,
            ),
            $filename
        );
    }
}
