<?php

namespace App\Http\Controllers;

use App\Models\ComisionDetalle;
use App\Models\ComisionUpload;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ComisionesExportController extends Controller
{
    public function exportar(int $uploadId): Response
    {
        $upload = ComisionUpload::findOrFail($uploadId);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // quitar hoja por defecto

        // Una hoja por vendedor
        $vendedores = ComisionDetalle::where('comision_upload_id', $uploadId)
            ->distinct()
            ->pluck('vendedor');

        foreach ($vendedores as $vendedor) {
            $ws = $spreadsheet->createSheet();
            // El nombre de hoja no puede tener más de 31 chars ni ciertos caracteres
            $ws->setTitle(substr(preg_replace('/[\/\\\\?*:\[\]]/', '-', $vendedor), 0, 31));

            $this->llenarHojaVendedor($ws, $upload, $vendedor);
        }

        // Hoja resumen al principio
        $wsResumen = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'RESUMEN');
        $spreadsheet->addSheet($wsResumen, 0);
        $this->llenarHojaResumen($wsResumen, $upload);

        $spreadsheet->setActiveSheetIndex(0);

        // Generar respuesta
        $writer   = new Xlsx($spreadsheet);
        $filename = 'Comisiones_' . str_replace('-', '_', $upload->periodo) . '.xlsx';

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function llenarHojaVendedor(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        ComisionUpload $upload,
        string $vendedor
    ): void {
        // ── Título ───────────────────────────────────────────────────────────
        $ws->setCellValue('A1', 'INDUSTRIAL PROCESS SRL — IN PROCESS SRL');
        $ws->setCellValue('A2', 'COMISIONES PERÍODO: ' . $upload->mes_nombre);
        $ws->setCellValue('A3', 'Vendedor: ' . $vendedor);
        $ws->setCellValue('A4', 'Porcentaje: 1.5%');

        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->getStyle('A2:A4')->getFont()->setBold(true);

        // ── Encabezados de columna ────────────────────────────────────────────
        $headers = [
            'A' => 'Comprobante',
            'B' => 'Cliente',
            'C' => 'Condición',
            'D' => 'F. Emisión',
            'E' => 'F. Pago',
            'F' => 'Estado',
            'G' => 'Base cobrada (S/)',
            'H' => 'Comisión 1.5% (S/)',
        ];

        $headerRow = 6;
        foreach ($headers as $col => $label) {
            $ws->setCellValue($col . $headerRow, $label);
        }

        $headerRange = 'A' . $headerRow . ':H' . $headerRow;
        $ws->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Datos ─────────────────────────────────────────────────────────────
        $detalles = ComisionDetalle::where('comision_upload_id', $upload->id)
            ->where('vendedor', $vendedor)
            ->orderBy('numdoc')
            ->get();

        $row = $headerRow + 1;
        foreach ($detalles as $d) {
            $ws->setCellValue('A' . $row, $d->numdoc);
            $ws->setCellValue('B' . $row, $d->razon_social);
            $ws->setCellValue('C' . $row, $d->condicion);
            $ws->setCellValue('D' . $row, $d->fecha_emision?->format('d/m/Y'));
            $ws->setCellValue('E' . $row, $d->fecha_pago?->format('d/m/Y') ?? '—');
            $ws->setCellValue('F' . $row, strtoupper($d->estado));
            $ws->setCellValue('G' . $row, $d->base_comision_cobrada ?? 0);
            $ws->setCellValue('H' . $row, $d->comision_calculada);

            // Color de fila según estado
            $fillColor = match($d->estado) {
                'cobrada'   => 'E8F5E9',
                'anulada'   => 'FFEBEE',
                default     => 'FFFDE7',
            };

            $ws->getStyle('A' . $row . ':H' . $row)
   ->getFill()
   ->setFillType(Fill::FILL_SOLID)
   ->getStartColor()
   ->setRGB($fillColor);
            $row++;
        }

        // ── Totales ───────────────────────────────────────────────────────────
        $row++;
        $ws->setCellValue('F' . $row, 'TOTAL:');
        $ws->setCellValue('G' . $row, "=SUM(G" . ($headerRow + 1) . ":G" . ($row - 2) . ")");
        $ws->setCellValue('H' . $row, "=SUM(H" . ($headerRow + 1) . ":H" . ($row - 2) . ")");

        $ws->getStyle('F' . $row . ':H' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => 'DDEEFF']],
        ]);

        // Formato moneda en columnas G y H
        $moneyFormat = '"S/ "#,##0.00';
        $dataRange = 'G' . ($headerRow + 1) . ':H' . $row;
        $ws->getStyle($dataRange)->getNumberFormat()->setFormatCode($moneyFormat);

        // Bordes en toda la tabla
        $ws->getStyle('A' . $headerRow . ':H' . ($row))
           ->getBorders()
           ->getAllBorders()
           ->setBorderStyle(Border::BORDER_THIN);

        // Ancho de columnas
        $ws->getColumnDimension('A')->setWidth(20);
        $ws->getColumnDimension('B')->setWidth(45);
        $ws->getColumnDimension('C')->setWidth(22);
        $ws->getColumnDimension('D')->setWidth(14);
        $ws->getColumnDimension('E')->setWidth(14);
        $ws->getColumnDimension('F')->setWidth(14);
        $ws->getColumnDimension('G')->setWidth(22);
        $ws->getColumnDimension('H')->setWidth(22);
    }

    private function llenarHojaResumen(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        ComisionUpload $upload
    ): void {
        $ws->setCellValue('A1', 'RESUMEN DE COMISIONES — ' . $upload->mes_nombre);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = ['Vendedor', 'Facturas', 'Cobradas', 'Pendientes', 'Base cobrada (S/)', 'Comisión (S/)'];
        foreach ($headers as $i => $h) {
            $ws->setCellValueByColumnAndRow($i + 1, 3, $h);
        }

        $ws->getStyle('A3:F3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $resumen = ComisionDetalle::where('comision_upload_id', $upload->id)
            ->selectRaw('vendedor, COUNT(*) as total, SUM(estado="cobrada") as cobradas, SUM(estado="pendiente") as pendientes, COALESCE(SUM(base_comision_cobrada),0) as base_cobrada, COALESCE(SUM(comision_calculada),0) as comision')
            ->groupBy('vendedor')
            ->orderBy('vendedor')
            ->get();

        $row = 4;
        foreach ($resumen as $v) {
            $ws->setCellValue('A' . $row, $v->vendedor);
            $ws->setCellValue('B' . $row, $v->total);
            $ws->setCellValue('C' . $row, $v->cobradas);
            $ws->setCellValue('D' . $row, $v->pendientes);
            $ws->setCellValue('E' . $row, $v->base_cobrada);
            $ws->setCellValue('F' . $row, $v->comision);
            $row++;
        }

        // Fila total
        $ws->setCellValue('A' . $row, 'TOTAL');
        $ws->setCellValue('E' . $row, "=SUM(E4:E" . ($row - 1) . ")");
        $ws->setCellValue('F' . $row, "=SUM(F4:F" . ($row - 1) . ")");
        $ws->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);

        $ws->getStyle('E4:F' . $row)->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
        $ws->getColumnDimension('A')->setWidth(40);
        $ws->getColumnDimension('B')->setWidth(12);
        $ws->getColumnDimension('C')->setWidth(12);
        $ws->getColumnDimension('D')->setWidth(12);
        $ws->getColumnDimension('E')->setWidth(22);
        $ws->getColumnDimension('F')->setWidth(22);
    }
}
