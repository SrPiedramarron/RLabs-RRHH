<?php

namespace App\Services;

use App\Models\ComisionDetalle;
use App\Models\ComisionUpload;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComisionesService
{
    // Fila donde están los encabezados en ambos archivos
    const HEADER_ROW = 5;
    // Primera fila de datos
    const DATA_START_ROW = 6;
    // Porcentaje fijo de comisión
    const PORCENTAJE = 0.0150;

    // Palabras clave que indican filas de subtotal/total (no son datos)
    const SKIP_PATTERNS = [
        'sub-total',
        'total general',
        'total vendedor',
    ];

    /**
     * Procesa los dos archivos Excel y guarda el resultado en BD.
     */
    public function procesar(ComisionUpload $upload, string $pathCobranzas, string $pathComisiones): void
    {
        try {
            $upload->update(['estado' => 'procesando']);

            $cobranzas  = $this->leerCobranzas($pathCobranzas);
            $comisiones = $this->leerComisiones($pathComisiones);

            $detalles = $this->cruzar($comisiones, $cobranzas, $upload);

            // Insertar en lotes de 200 para no saturar memoria
            foreach ($detalles->chunk(200) as $chunk) {
                ComisionDetalle::insert($chunk->toArray());
            }

            // Recalcular totales en el upload
            $stats = ComisionDetalle::where('comision_upload_id', $upload->id)
                ->selectRaw('
                    COUNT(*) as total_facturas,
                    SUM(estado = "cobrada") as total_cobradas,
                    SUM(estado = "pendiente") as total_pendientes,
                    SUM(estado = "anulada") as total_anuladas,
                    COALESCE(SUM(base_comision_cobrada), 0) as total_base_cobrada,
                    COALESCE(SUM(comision_calculada), 0) as total_comision
                ')
                ->first();

            $upload->update([
                'estado'             => 'completado',
                'total_facturas'     => $stats->total_facturas,
                'total_cobradas'     => $stats->total_cobradas,
                'total_pendientes'   => $stats->total_pendientes,
                'total_anuladas'     => $stats->total_anuladas,
                'total_base_cobrada' => $stats->total_base_cobrada,
                'total_comision'     => $stats->total_comision,
            ]);

        } catch (\Throwable $e) {
            $upload->update([
                'estado'         => 'error',
                'error_mensaje'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Lee el Excel de cobranzas y retorna un Collection indexado por numdoc.
     * Maneja múltiples hojas (MARZO 2026, ABRIL 2026, MAYO 2026).
     * Retorna: numdoc => [base_comision, fecha_pago, forma_pago, importe_cobrado]
     */
    private function leerCobranzas(string $path): Collection
    {
        $spreadsheet = IOFactory::load($path);
        $resultado   = collect();

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            // Ignorar hojas que no sean meses
            if (stripos($sheetName, 'resumen') !== false) {
                continue;
            }

            $ws = $spreadsheet->getSheetByName($sheetName);
            $rows = $this->leerFilas($ws, [
                'vendedor'       => 0,   // col A
                'fecha_pago'     => 1,   // col B
                'tipo_doc'       => 2,   // col C
                'numdoc'         => 3,   // col D
                'cobrado'        => 10,  // col K
                'forma_pago'     => 12,  // col M
                'importe'        => 14,  // col O — Importe.Cmpbte
                'base_comision'  => 15,  // col P — Base_Comisión
            ]);

            foreach ($rows as $row) {
                $numdoc = trim($row['numdoc'] ?? '');
                if (empty($numdoc)) {
                    continue;
                }

                // Puede haber múltiples cobros parciales del mismo comprobante;
                // acumulamos la base de comisión cobrada
                if ($resultado->has($numdoc)) {
                    $existing = $resultado->get($numdoc);
                    $existing['base_comision'] += floatval($row['base_comision'] ?? 0);
                    $existing['importe_cobrado'] += floatval($row['cobrado'] ?? 0);
                    $resultado->put($numdoc, $existing);
                } else {
                    $resultado->put($numdoc, [
                        'base_comision'   => floatval($row['base_comision'] ?? 0),
                        'fecha_pago'      => $this->parseDate($row['fecha_pago']),
                        'forma_pago'      => trim($row['forma_pago'] ?? ''),
                        'importe_cobrado' => floatval($row['cobrado'] ?? 0),
                    ]);
                }
            }
        }

        return $resultado;
    }

    /**
     * Lee el Excel de comisiones y retorna todas las filas de facturas.
     */
    private function leerComisiones(string $path): Collection
    {
        $spreadsheet = IOFactory::load($path);
        $resultado   = collect();

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (stripos($sheetName, 'resumen') !== false) {
                continue;
            }

            // Extraer el periodo de la hoja, e.g. "MARZO 2026" → "2026-03"
            $periodo = $this->parsePeriodo($sheetName);

            $ws = $spreadsheet->getSheetByName($sheetName);
            $rows = $this->leerFilas($ws, [
                'vendedor'        => 0,   // col A
                'fecha_emision'   => 1,   // col B
                'tipo_doc'        => 2,   // col C
                'numdoc'          => 3,   // col D
                'cod_cliente'     => 4,   // col E
                'razon_social'    => 5,   // col F
                'condicion'       => 6,   // col G
                'fecha_venc'      => 7,   // col H
                'moneda'          => 8,   // col I
                'v_contado'       => 9,   // col J
                'v_credito'       => 10,  // col K
                'tipo_cambio'     => 11,  // col L
                'base_comision'   => 12,  // col M — Base_Comisión
                'mes_cobro'       => 13,  // col N — "ABRIL", "MAYO", etc.
            ]);

            foreach ($rows as $row) {
                $numdoc = trim($row['numdoc'] ?? '');
                if (empty($numdoc)) {
                    continue;
                }

                $resultado->push([
                    'periodo'             => $periodo,
                    'hoja'                => $sheetName,
                    'vendedor'            => $this->limpiarVendedor($row['vendedor'] ?? ''),
                    'numdoc'              => $numdoc,
                    'tipo_doc'            => trim($row['tipo_doc'] ?? ''),
                    'cod_cliente'         => trim($row['cod_cliente'] ?? ''),
                    'razon_social'        => trim($row['razon_social'] ?? ''),
                    'condicion'           => trim($row['condicion'] ?? ''),
                    'fecha_emision'       => $this->parseDate($row['fecha_emision']),
                    'fecha_vencimiento'   => $this->parseDate($row['fecha_venc']),
                    'moneda'              => trim($row['moneda'] ?? 'S/'),
                    'tipo_cambio'         => floatval($row['tipo_cambio'] ?? 1),
                    'base_comision_venta' => floatval($row['base_comision'] ?? 0),
                    'v_venta_contado'     => floatval($row['v_contado'] ?? 0),
                    'v_venta_credito'     => floatval($row['v_credito'] ?? 0),
                    'mes_cobro'           => trim($row['mes_cobro'] ?? ''),
                ]);
            }
        }

        return $resultado;
    }

    /**
     * Cruza comisiones con cobranzas y devuelve los detalles listos para insertar.
     */
    private function cruzar(Collection $comisiones, Collection $cobranzas, ComisionUpload $upload): Collection
{
    $now = now();
    $resultado = collect();

    // numdocs ya procesados desde el archivo de comisiones
    $numdocsEnComisiones = $comisiones->pluck('numdoc')->map(fn($n) => trim($n))->flip();

    // -- Cruce normal (facturas del mes actual) ----------------------------
    foreach ($comisiones as $com) {
        $cobrada = $cobranzas->get($com['numdoc']);

        $esAnulada = str_contains(strtolower($com['razon_social'] ?? ''), 'anulado')
                  || ($com['base_comision_venta'] == 0 && empty($com['mes_cobro']));

        if ($esAnulada) {
            $estado = 'anulada';
        } elseif ($cobrada !== null) {
            $estado = 'cobrada';
        } else {
            $estado = 'pendiente';
        }

        $baseCobrada  = $cobrada ? floatval($cobrada['base_comision']) : null;
        $comisionCalc = $baseCobrada !== null ? round($baseCobrada * self::PORCENTAJE, 2) : 0;

        $resultado->push([
            'comision_upload_id'     => $upload->id,
            'periodo'                => $com['periodo'],
            'vendedor'               => $com['vendedor'],
            'numdoc'                 => $com['numdoc'],
            'tipo_doc'               => $com['tipo_doc'],
            'cod_cliente'            => $com['cod_cliente'],
            'razon_social'           => $com['razon_social'],
            'condicion'              => $com['condicion'],
            'fecha_emision'          => $com['fecha_emision'],
            'fecha_vencimiento'      => $com['fecha_vencimiento'],
            'moneda'                 => $com['moneda'],
            'tipo_cambio'            => $com['tipo_cambio'],
            'base_comision_venta'    => $com['base_comision_venta'],
            'v_venta_contado'        => $com['v_venta_contado'],
            'v_venta_credito'        => $com['v_venta_credito'],
            'base_comision_cobrada'  => $baseCobrada,
            'fecha_pago'             => $cobrada['fecha_pago'] ?? null,
            'forma_pago'             => $cobrada['forma_pago'] ?? null,
            'importe_cobrado'        => $cobrada['importe_cobrado'] ?? null,
            'estado'                 => $estado,
            'mes_cobro'              => $com['mes_cobro'] ?: null,
            'comision_calculada'     => $comisionCalc,
            'porcentaje_comision'    => self::PORCENTAJE,
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);
    }

    // -- Cobradas hu�rfanas: est�n en cobranzas pero NO en comisiones del mes -
    // Son facturas de meses anteriores que reci�n se cobran este mes.
    foreach ($cobranzas as $numdoc => $cobrada) {
        if ($numdocsEnComisiones->has($numdoc)) {
            continue; // ya procesada arriba
        }

        // Buscar el detalle original en BD (periodos anteriores)
        $detailAnterior = \App\Models\ComisionDetalle::where('numdoc', $numdoc)
            ->whereIn('estado', ['pendiente'])
            ->orderBy('periodo', 'asc')
            ->first();

        if ($detailAnterior) {
            // Factura conocida � usar datos originales
            $baseCobrada  = floatval($cobrada['base_comision']);
            $comisionCalc = round($baseCobrada * self::PORCENTAJE, 2);

            $resultado->push([
                'comision_upload_id'     => $upload->id,
                'periodo'                => $upload->periodo,
                'vendedor'               => $detailAnterior->vendedor,
                'numdoc'                 => $numdoc,
                'tipo_doc'               => $detailAnterior->tipo_doc,
                'cod_cliente'            => $detailAnterior->cod_cliente,
                'razon_social'           => $detailAnterior->razon_social,
                'condicion'              => $detailAnterior->condicion,
                'fecha_emision'          => $detailAnterior->fecha_emision,
                'fecha_vencimiento'      => $detailAnterior->fecha_vencimiento,
                'moneda'                 => $detailAnterior->moneda,
                'tipo_cambio'            => $detailAnterior->tipo_cambio,
                'base_comision_venta'    => $detailAnterior->base_comision_venta,
                'v_venta_contado'        => $detailAnterior->v_venta_contado,
                'v_venta_credito'        => $detailAnterior->v_venta_credito,
                'base_comision_cobrada'  => $baseCobrada,
                'fecha_pago'             => $cobrada['fecha_pago'] ?? null,
                'forma_pago'             => $cobrada['forma_pago'] ?? null,
                'importe_cobrado'        => $cobrada['importe_cobrado'] ?? null,
                'estado'                 => 'cobrada',
                'mes_cobro'              => null,
                'comision_calculada'     => $comisionCalc,
                'porcentaje_comision'    => self::PORCENTAJE,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            // Marcar el pendiente anterior como cobrado en su periodo original
            $detailAnterior->update([
                'estado'                => 'cobrada',
                'base_comision_cobrada' => $baseCobrada,
                'fecha_pago'            => $cobrada['fecha_pago'] ?? null,
                'comision_calculada'    => $comisionCalc,
            ]);

        } else {
            // Factura desconocida � no est� en ning�n periodo cargado
            // La agregamos igual para que no se pierda la comisi�n
            $baseCobrada  = floatval($cobrada['base_comision']);
            $comisionCalc = round($baseCobrada * self::PORCENTAJE, 2);

            $resultado->push([
                'comision_upload_id'     => $upload->id,
                'periodo'                => $upload->periodo,
                'vendedor'               => 'Oficina', // no tenemos datos del vendedor
                'numdoc'                 => $numdoc,
                'tipo_doc'               => '',
                'cod_cliente'            => null,
                'razon_social'           => '(factura de periodo anterior sin datos)',
                'condicion'              => null,
                'fecha_emision'          => null,
                'fecha_vencimiento'      => null,
                'moneda'                 => 'S/',
                'tipo_cambio'            => 1,
                'base_comision_venta'    => 0,
                'v_venta_contado'        => 0,
                'v_venta_credito'        => 0,
                'base_comision_cobrada'  => $baseCobrada,
                'fecha_pago'             => $cobrada['fecha_pago'] ?? null,
                'forma_pago'             => $cobrada['forma_pago'] ?? null,
                'importe_cobrado'        => $cobrada['importe_cobrado'] ?? null,
                'estado'      		=> 'huerfana', 
    		'razon_social' 		=> '? Factura no encontrada en Excel de comisiones',
                'mes_cobro'              => null,
                'comision_calculada'     => $comisionCalc,
                'porcentaje_comision'    => self::PORCENTAJE,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }

    return $resultado;
}
    /**
     * Lee las filas de datos de una hoja, saltando encabezados y subtotales.
     */
    private function leerFilas(Worksheet $ws, array $columnas): array
    {
        $resultado  = [];
        $highestRow = $ws->getHighestDataRow();

        for ($rowNum = self::DATA_START_ROW; $rowNum <= $highestRow; $rowNum++) {
            $primeraCol = trim((string) $ws->getCellByColumnAndRow(1, $rowNum)->getValue());

            // Saltar filas vacías o de subtotal/total
            if (empty($primeraCol) || $this->esFilaSkip($primeraCol)) {
                continue;
            }

            $fila = [];
            foreach ($columnas as $key => $colIndex) {
                // PhpSpreadsheet usa índice base 1
                $cell  = $ws->getCellByColumnAndRow($colIndex + 1, $rowNum);
                $value = $cell->getValue();

                // Si el valor es una fecha Excel (número), convertir
                if (is_float($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('d/m/Y');
                }

                $fila[$key] = $value;
            }

            $resultado[] = $fila;
        }

        return $resultado;
    }

    private function esFilaSkip(string $valor): bool
    {
        $lower = strtolower($valor);
        foreach (self::SKIP_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convierte "MARZO 2026" → "2026-03"
     */
    private function parsePeriodo(string $sheetName): string
    {
        $meses = [
            'ENERO' => '01', 'FEBRERO' => '02', 'MARZO'  => '03',
            'ABRIL' => '04', 'MAYO'    => '05', 'JUNIO'  => '06',
            'JULIO' => '07', 'AGOSTO'  => '08', 'SEPTIEMBRE' => '09',
            'OCTUBRE' => '10', 'NOVIEMBRE' => '11', 'DICIEMBRE' => '12',
        ];

        preg_match('/([A-ZÁÉÍÓÚ]+)\s+(\d{4})/u', strtoupper($sheetName), $matches);

        if (isset($matches[1], $matches[2]) && isset($meses[$matches[1]])) {
            return $matches[2] . '-' . $meses[$matches[1]];
        }

        return $sheetName; // fallback
    }

    /**
     * Limpia el nombre del vendedor (viene con espacios padding del ERP).
     */
    private function limpiarVendedor(string $raw): string
    {
        return trim($raw);
    }

    /**
     * Parsea una fecha que puede venir como string "dd/mm/yyyy" o valor Excel numérico.
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            try {
                return Carbon::createFromFormat('d/m/Y', trim($value))->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        if (is_float($value) || is_int($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }

        return null;
    }
}
