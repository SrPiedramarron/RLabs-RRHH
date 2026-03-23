<?php

namespace App\Exports;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Location;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class AttendanceExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithCustomStartCell
{
    private int $rowCount = 0;
    private string $companyName = '';
    private string $locationName = '';

    public function __construct(
        private int    $companyId,
        private ?int   $locationId,
        private string $desde,
        private string $hasta,
        private bool   $incluirRefrigerio = false,
    ) {
        $company = Company::find($companyId);
        $this->companyName = $company ? $company->razon_social : '';
        if ($locationId) {
            $location = Location::find($locationId);
            $this->locationName = $location ? $location->nombre : '';
        } else {
            $this->locationName = 'Todas las sedes';
        }
    }

    private function decimalToHHMM(?float $decimal): string
    {
        if (!$decimal || $decimal <= 0) return '—';
        $total = (int) round($decimal * 60);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

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
        $headers = ['N°', 'APELLIDOS Y NOMBRES', 'DNI', 'FECHA', 'HORA INGRESO', 'HORA SALIDA'];
        if ($this->incluirRefrigerio) {
            $headers[] = 'INICIO REFRIGERIO';
            $headers[] = 'FINAL REFRIGERIO';
        }
        $headers[] = 'HORAS LABORADAS';
        $headers[] = 'HORAS EXTRAS 25%';
        $headers[] = 'HORAS EXTRAS 35%';
        return $headers;
    }

    public function map($record): array
    {
        $this->rowCount++;
        $row = [
            $this->rowCount,
            strtoupper($record->employee->nombre_completo),
            $record->employee->dni,
            $record->fecha->format('d/m/Y'),
            $record->hora_entrada?->format('H:i') ?? '—',
            $record->hora_salida?->format('H:i') ?? '—',
        ];

        if ($this->incluirRefrigerio) {
            $row[] = $record->inicio_refrigerio ? Carbon::parse($record->inicio_refrigerio)->format('H:i') : '—';
            $row[] = $record->fin_refrigerio    ? Carbon::parse($record->fin_refrigerio)->format('H:i')    : '—';
        }

        $row[] = $this->decimalToHHMM($record->horas_ordinarias);
        $row[] = $this->decimalToHHMM($record->horas_extra_diurnas);
        $row[] = $this->decimalToHHMM($record->horas_extra_nocturnas);
        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'REGISTRO DE CONTROL DE ASISTENCIA');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'Res. Ministerial N° 020-2001-TR');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('A3', 'Razón Social:');
        $sheet->setCellValue('B3', $this->companyName);
        $sheet->setCellValue('A4', 'Sede:');
        $sheet->setCellValue('B4', $this->locationName);
        $sheet->setCellValue('A5', 'Periodo:');
        $sheet->setCellValue('B5', Carbon::parse($this->desde)->format('d/m/Y') . ' al ' . Carbon::parse($this->hasta)->format('d/m/Y'));
        $sheet->getStyle('A3:A5')->applyFromArray(['font' => ['bold' => true]]);

        $lastCol = $this->incluirRefrigerio ? 'K' : 'I';
        $sheet->getStyle("A7:{$lastCol}7")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C0392B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        return [];
    }

    public function startCell(): string
    {
        return 'A7';
    }

    public function title(): string
    {
        return 'Registro de Asistencia';
    }
}
