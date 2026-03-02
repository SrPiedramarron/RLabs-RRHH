<?php

namespace App\Exports;

use App\Models\AttendanceRecord;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private int $companyId,
        private ?int $locationId,
        private string $desde,
        private string $hasta,
    ) {}

    public function query()
    {
        return AttendanceRecord::with(['employee', 'location'])
            ->where('company_id', $this->companyId)
            ->when($this->locationId, fn($q) => $q->where('location_id', $this->locationId))
            ->whereBetween('fecha', [$this->desde, $this->hasta])
            ->whereHas('employee', fn($q) => $q->where('exonerado_registro', false))
            ->orderBy('fecha')
            ->orderBy('employee_id');
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'APELLIDOS Y NOMBRES',
            'DNI',
            'CARGO',
            'SEDE',
            'HORA ENTRADA',
            'HORA SALIDA',
            'TARDANZA (min)',
            'H. ORDINARIAS',
            'H. EXTRA DIURNAS (25%)',
            'H. EXTRA NOCTURNAS (35%)',
            'ESTADO',
            'JUSTIFICADO',
            'OBSERVACIÓN',
        ];
    }

    public function map($record): array
    {
        return [
            $record->fecha->format('d/m/Y'),
            $record->employee->nombre_completo,
            $record->employee->dni,
            $record->employee->cargo ?? '—',
            $record->location->nombre,
            $record->hora_entrada?->format('H:i') ?? '—',
            $record->hora_salida?->format('H:i') ?? '—',
            $record->minutos_tarde > 0 ? $record->minutos_tarde : '—',
            $record->horas_ordinarias,
            $record->horas_extra_diurnas,
            $record->horas_extra_nocturnas,
            strtoupper($record->estado),
            $record->justificado ? 'SÍ' : 'NO',
            $record->observacion ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a7f4b']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    public function title(): string
    {
        return 'Registro de Asistencia';
    }
}