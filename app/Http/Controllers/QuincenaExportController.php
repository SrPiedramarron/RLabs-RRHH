<?php

namespace App\Http\Controllers;

use App\Models\PlanillaQuincena;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class QuincenaExportController extends Controller
{
    public function exportar(Request $request): Response
    {
        $periodo   = $request->query('periodo');
        $companyId = (int) $request->query('company_id');

        abort_if(empty($periodo) || empty($companyId), 400, 'Faltan parámetros periodo o company_id.');

        $quincenas = PlanillaQuincena::where('periodo', $periodo)
            ->where('company_id', $companyId)
            ->orderBy('apellidos')
            ->get();

        abort_if($quincenas->isEmpty(), 404, 'No hay quincenas calculadas para este periodo.');

        $spreadsheet = new Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $mesNombre   = $quincenas->first()->mes_nombre;
        $ws->setTitle('Quincena ' . $mesNombre);

        $ws->setCellValue('A1', 'ADELANTO DE QUINCENA (DÍA 15) - ' . $mesNombre);
        $ws->mergeCells('A1:J1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'A' => 'Apellidos y Nombres',
            'B' => 'DNI',
            'C' => 'Cargo',
            'D' => 'Sueldo Base',
            'E' => 'Asig. Familiar',
            'F' => 'Base ÷ 2',
            'G' => 'Sistema Pension',
            'H' => 'AFP/ONP (÷2)',
            'I' => 'Renta 5ta (÷2)',
            'J' => 'Neto a Depositar',
        ];

        $headerRow = 3;
        foreach ($headers as $col => $label) {
            $ws->setCellValue($col . $headerRow, $label);
        }

        $ws->getStyle('A' . $headerRow . ':J' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension($headerRow)->setRowHeight(30);

        $row = $headerRow + 1;
        foreach ($quincenas as $q) {
            $ws->setCellValue('A' . $row, $q->apellidos . ', ' . $q->nombres);
            $ws->setCellValue('B' . $row, $q->dni);
            $ws->setCellValue('C' . $row, $q->cargo);
            $ws->setCellValue('D' . $row, $q->sueldo_base);
            $ws->setCellValue('E' . $row, $q->asignacion_familiar);
            $ws->setCellValue('F' . $row, $q->base_quincenal);
            $ws->setCellValue('G' . $row, match ($q->sistema_pensiones) {
                'onp'           => 'ONP',
                'afp_prima'     => 'AFP Prima',
                'afp_integra'   => 'AFP Integra',
                'afp_habitat'   => 'AFP Habitat',
                'afp_profuturo' => 'AFP Profuturo',
                default         => $q->sistema_pensiones,
            });
            $ws->setCellValue('H' . $row, $q->descuento_pension);
            $ws->setCellValue('I' . $row, $q->descuento_5ta_categoria);
            $ws->setCellValue('J' . $row, $q->neto_pagar);

            if ($row % 2 === 0) {
                $ws->getStyle('A' . $row . ':J' . $row)
                   ->getFill()->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setRGB('F0F4FA');
            }

            $row++;
        }

        $row++;
        $ws->setCellValue('A' . $row, 'TOTALES');
        foreach (['D', 'E', 'F', 'H', 'I', 'J'] as $col) {
            $ws->setCellValue($col . $row, "=SUM({$col}4:{$col}" . ($row - 2) . ")");
        }

        $ws->getStyle('A' . $row . ':J' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => 'DDEEFF']],
        ]);

        $moneyFormat = '"S/ "#,##0.00';
        foreach (['D', 'E', 'F', 'H', 'I', 'J'] as $col) {
            $ws->getStyle($col . '4:' . $col . $row)
               ->getNumberFormat()->setFormatCode($moneyFormat);
        }

        $ws->getStyle('A' . $headerRow . ':J' . $row)
           ->getBorders()->getAllBorders()
           ->setBorderStyle(Border::BORDER_THIN);

        $ws->getColumnDimension('A')->setWidth(35);
        $ws->getColumnDimension('B')->setWidth(12);
        $ws->getColumnDimension('C')->setWidth(20);
        foreach (['D', 'E', 'F', 'H', 'I', 'J'] as $c) {
            $ws->getColumnDimension($c)->setWidth(16);
        }
        $ws->getColumnDimension('G')->setWidth(14);

        $ws->freezePane('A4');

        $writer   = new Xlsx($spreadsheet);
        $filename = 'Quincena_' . str_replace('-', '_', $periodo) . '.xlsx';

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
