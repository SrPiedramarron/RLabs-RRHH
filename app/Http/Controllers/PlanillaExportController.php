<?php

namespace App\Http\Controllers;

use App\Models\PlanillaLiquidacion;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlanillaExportController extends Controller
{
    public function exportar(string $periodo, int $companyId): Response
    {
        $liquidaciones = PlanillaLiquidacion::where('periodo', $periodo)
            ->where('company_id', $companyId)
            ->orderBy('apellidos')
            ->get();

        abort_if($liquidaciones->isEmpty(), 404, 'No hay liquidaciones para este periodo.');

        $spreadsheet = new Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $mesNombre   = $liquidaciones->first()->mes_nombre;
        $ws->setTitle($mesNombre);

        // ── Titulo ────────────────────────────────────────────────────────────
        $ws->setCellValue('A1', 'PLANILLA DE REMUNERACIONES - ' . $mesNombre);
        $ws->mergeCells('A1:R1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── Encabezados ───────────────────────────────────────────────────────
        $headers = [
            'A' => 'Apellidos y Nombres',
            'B' => 'DNI',
            'C' => 'Cargo',
            'D' => 'Sueldo Base',
            'E' => 'Dias Falta',
            'F' => 'Min. Tarde',
            'G' => 'H.E. Diurnas',
            'H' => 'H.E. Nocturnas',
            'I' => 'Sueldo Prop.',
            'J' => 'Importe H.E.',
            'K' => 'Comisiones',
            'L' => 'Bonos',
            'M' => 'Desc. Tardanza',
            'N' => 'Rem. Bruta',
            'O' => 'Sistema Pension',
            'P' => 'Desc. Pension',
            'Q' => 'Desc. 5ta Cat.',
            'R' => 'Neto a Pagar',
        ];

        $headerRow = 3;
        foreach ($headers as $col => $label) {
            $ws->setCellValue($col . $headerRow, $label);
        }

        $ws->getStyle('A' . $headerRow . ':R' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension($headerRow)->setRowHeight(30);

        // ── Datos ─────────────────────────────────────────────────────────────
        $row = $headerRow + 1;
        foreach ($liquidaciones as $l) {
            $ws->setCellValue('A' . $row, $l->apellidos . ', ' . $l->nombres);
            $ws->setCellValue('B' . $row, $l->dni);
            $ws->setCellValue('C' . $row, $l->cargo);
            $ws->setCellValue('D' . $row, $l->sueldo_base);
            $ws->setCellValue('E' . $row, $l->dias_falta);
            $ws->setCellValue('F' . $row, $l->total_minutos_tarde);
            $ws->setCellValue('G' . $row, $l->horas_extra_diurnas);
            $ws->setCellValue('H' . $row, $l->horas_extra_nocturnas);
            $ws->setCellValue('I' . $row, $l->sueldo_proporcional);
            $ws->setCellValue('J' . $row, $l->importe_horas_extra_diurnas + $l->importe_horas_extra_nocturnas);
            $ws->setCellValue('K' . $row, $l->comisiones);
            $ws->setCellValue('L' . $row, $l->bonos_especiales);
            $ws->setCellValue('M' . $row, $l->descuento_tardanzas);
            $ws->setCellValue('N' . $row, $l->remuneracion_bruta);
            $ws->setCellValue('O' . $row, $l->sistema_pensiones_label);
            $ws->setCellValue('P' . $row, $l->descuento_pension);
            $ws->setCellValue('Q' . $row, $l->descuento_5ta_categoria);
            $ws->setCellValue('R' . $row, $l->neto_pagar);

            // Alternar fila
            if ($row % 2 === 0) {
                $ws->getStyle('A' . $row . ':R' . $row)
                   ->getFill()->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setRGB('F0F4FA');
            }

            $row++;
        }

        // ── Fila totales ──────────────────────────────────────────────────────
        $row++;
        $ws->setCellValue('A' . $row, 'TOTALES');
        $ws->setCellValue('N' . $row, "=SUM(N4:N" . ($row - 2) . ")");
        $ws->setCellValue('P' . $row, "=SUM(P4:P" . ($row - 2) . ")");
        $ws->setCellValue('Q' . $row, "=SUM(Q4:Q" . ($row - 2) . ")");
        $ws->setCellValue('R' . $row, "=SUM(R4:R" . ($row - 2) . ")");

        $ws->getStyle('A' . $row . ':R' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => 'DDEEFF']],
        ]);

        // ── Formato moneda ────────────────────────────────────────────────────
        $moneyFormat = '"S/ "#,##0.00';
        $moneyCols   = ['D', 'I', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R'];
        foreach ($moneyCols as $col) {
            $ws->getStyle($col . '4:' . $col . $row)
               ->getNumberFormat()->setFormatCode($moneyFormat);
        }

        // ── Bordes ────────────────────────────────────────────────────────────
        $ws->getStyle('A' . $headerRow . ':R' . $row)
           ->getBorders()->getAllBorders()
           ->setBorderStyle(Border::BORDER_THIN);

        // ── Anchos ────────────────────────────────────────────────────────────
        $ws->getColumnDimension('A')->setWidth(35);
        $ws->getColumnDimension('B')->setWidth(12);
        $ws->getColumnDimension('C')->setWidth(20);
        foreach (['D','I','J','K','L','M','N','P','Q','R'] as $c) {
            $ws->getColumnDimension($c)->setWidth(16);
        }
        $ws->getColumnDimension('E')->setWidth(10);
        $ws->getColumnDimension('F')->setWidth(10);
        $ws->getColumnDimension('G')->setWidth(12);
        $ws->getColumnDimension('H')->setWidth(12);
        $ws->getColumnDimension('O')->setWidth(14);

        $ws->freezePane('A4');

        // ── Output ────────────────────────────────────────────────────────────
        $writer   = new Xlsx($spreadsheet);
        $filename = 'Planilla_' . str_replace('-', '_', $periodo) . '.xlsx';

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
