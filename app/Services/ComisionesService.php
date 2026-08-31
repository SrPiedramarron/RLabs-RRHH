<?php

namespace App\Services;

use App\Models\ComisionDetalle;
use App\Models\ComisionUpload;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComisionesService
{
    const HEADER_ROW = 5;
    const DATA_START_ROW = 6;
    const PORCENTAJE = 0.0150;

    const SKIP_PATTERNS = [
        'sub-total',
        'total general',
        'total vendedor',
    ];

    /** Mapa normalizado nombre => employee_id, construido una vez por procesar(). */
    private ?array $mapaEmpleados = null;

    /** Nombres de 'vendedor' que no matchearon ningún empleado (para la notificación). */
    private array $noMatcheados = [];

    public function procesar(ComisionUpload $upload, string $pathCobranzas, string $pathComisiones): void
    {
        try {
            $upload->update(['estado' => 'procesando']);

            $this->noMatcheados = [];
            $this->construirMapaEmpleados($upload->company_id);

            $cobranzas  = $this->leerCobranzas($pathCobranzas);
            $comisiones = $this->leerComisiones($pathComisiones);

            $detalles = $this->cruzar($comisiones, $cobranzas, $upload);

            foreach ($detalles->chunk(200) as $chunk) {
                ComisionDetalle::insert($chunk->toArray());
            }

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
                'estado'                => 'completado',
                'total_facturas'        => $stats->total_facturas,
                'total_cobradas'        => $stats->total_cobradas,
                'total_pendientes'      => $stats->total_pendientes,
                'total_anuladas'        => $stats->total_anuladas,
                'total_base_cobrada'    => $stats->total_base_cobrada,
                'total_comision'        => $stats->total_comision,
                'vendedores_sin_match'  => array_values(array_unique($this->noMatcheados)) ?: null,
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
     * Construye el mapa normalizado nombre-completo => employee_id, una sola vez.
     * Cubre "NOMBRES APELLIDOS" y "APELLIDOS NOMBRES" porque no sabemos en qué
     * orden viene el texto del Excel de comisiones.
     */
    private function construirMapaEmpleados(?int $companyId): void
    {
        $this->mapaEmpleados = [];

        Employee::where('active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'nombres', 'apellidos'])->each(function ($e) {
            $orden1 = $this->normalizarNombre($e->nombres . ' ' . $e->apellidos);
            $orden2 = $this->normalizarNombre($e->apellidos . ' ' . $e->nombres);
            $this->mapaEmpleados[$orden1] = $e->id;
            $this->mapaEmpleados[$orden2] = $e->id;
        });
    }

    /**
     * Normaliza: mayúsculas, sin tildes, espacios colapsados, sin espacios en los bordes.
     */
    private function normalizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
        return preg_replace('/\s+/', ' ', $nombre);
    }

    /**
     * Resuelve el employee_id a partir del texto 'vendedor' del Excel.
     * Si no matchea, lo registra en $this->noMatcheados y devuelve null
     * (la comisión igual se guarda, solo que sin empleado asociado —
     * PlanillaService la ignorará hasta que se corrija el nombre).
     */
    private function resolverEmployeeId(string $vendedor): ?int
    {
        $vendedor = trim($vendedor);

        if ($vendedor === '' || strtolower($vendedor) === 'oficina') {
            return null; // "Oficina" es un valor válido de no-vendedor, no un error
        }

        $normalizado = $this->normalizarNombre($vendedor);
        $id = $this->mapaEmpleados[$normalizado] ?? null;

        if ($id === null) {
            $this->noMatcheados[] = $vendedor;
        }

        return $id;
    }

    private function leerCobranzas(string $path): Collection
    {
        $spreadsheet = IOFactory::load($path);
        $resultado   = collect();

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (stripos($sheetName, 'resumen') !== false) {
                continue;
            }

            $ws = $spreadsheet->getSheetByName($sheetName);
            $rows = $this->leerFilas($ws, [
                'vendedor'       => 0,
                'fecha_pago'     => 1,
                'tipo_doc'       => 2,
                'numdoc'         => 3,
                'cobrado'        => 10,
                'forma_pago'     => 12,
                'importe'        => 14,
                'base_comision'  => 15,
            ]);

            foreach ($rows as $row) {
                $numdoc = trim($row['numdoc'] ?? '');
                if (empty($numdoc)) {
                    continue;
                }

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

    private function leerComisiones(string $path): Collection
    {
        $spreadsheet = IOFactory::load($path);
        $resultado   = collect();

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (stripos($sheetName, 'resumen') !== false) {
                continue;
            }

            $periodo = $this->parsePeriodo($sheetName);

            $ws = $spreadsheet->getSheetByName($sheetName);
            $rows = $this->leerFilas($ws, [
                'vendedor'        => 0,
                'fecha_emision'   => 1,
                'tipo_doc'        => 2,
                'numdoc'          => 3,
                'cod_cliente'     => 4,
                'razon_social'    => 5,
                'condicion'       => 6,
                'fecha_venc'      => 7,
                'moneda'          => 8,
                'v_contado'       => 9,
                'v_credito'       => 10,
                'tipo_cambio'     => 11,
                'base_comision'   => 12,
                'mes_cobro'       => 13,
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

    private function cruzar(Collection $comisiones, Collection $cobranzas, ComisionUpload $upload): Collection
    {
        $now = now();
        $resultado = collect();

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
                'employee_id'            => $this->resolverEmployeeId($com['vendedor']),
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

        // -- Cobradas huérfanas: están en cobranzas pero NO en comisiones del mes -
        foreach ($cobranzas as $numdoc => $cobrada) {
            if ($numdocsEnComisiones->has($numdoc)) {
                continue;
            }

            $detailAnterior = \App\Models\ComisionDetalle::where('numdoc', $numdoc)
                ->whereIn('estado', ['pendiente'])
                ->orderBy('periodo', 'asc')
                ->first();

            if ($detailAnterior) {
                $baseCobrada  = floatval($cobrada['base_comision']);
                $comisionCalc = round($baseCobrada * self::PORCENTAJE, 2);

                // El vendedor ya se conoce del detalle anterior — reutilizamos su
                // employee_id ya resuelto en vez de volver a matchear por texto.
                $resultado->push([
                    'comision_upload_id'     => $upload->id,
                    'periodo'                => $upload->periodo,
                    'vendedor'               => $detailAnterior->vendedor,
                    'employee_id'            => $detailAnterior->employee_id,
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

                $detailAnterior->update([
                    'estado'                => 'cobrada',
                    'base_comision_cobrada' => $baseCobrada,
                    'fecha_pago'            => $cobrada['fecha_pago'] ?? null,
                    'comision_calculada'    => $comisionCalc,
                ]);

            } else {
                $baseCobrada  = floatval($cobrada['base_comision']);
                $comisionCalc = round($baseCobrada * self::PORCENTAJE, 2);

                $resultado->push([
                    'comision_upload_id'     => $upload->id,
                    'periodo'                => $upload->periodo,
                    'vendedor'               => 'Oficina',
                    'employee_id'            => null,
                    'numdoc'                 => $numdoc,
                    'tipo_doc'               => '',
                    'cod_cliente'            => null,
                    'razon_social'           => '¿ Factura no encontrada en Excel de comisiones',
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
                    'estado'                 => 'huerfana',
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

    private function leerFilas(Worksheet $ws, array $columnas): array
    {
        $resultado  = [];
        $highestRow = $ws->getHighestDataRow();

        for ($rowNum = self::DATA_START_ROW; $rowNum <= $highestRow; $rowNum++) {
            $primeraCol = trim((string) $ws->getCellByColumnAndRow(1, $rowNum)->getValue());

            if (empty($primeraCol) || $this->esFilaSkip($primeraCol)) {
                continue;
            }

            $fila = [];
            foreach ($columnas as $key => $colIndex) {
                $cell  = $ws->getCellByColumnAndRow($colIndex + 1, $rowNum);
                $value = $cell->getValue();

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

        return $sheetName;
    }

    private function limpiarVendedor(string $raw): string
    {
        return trim($raw);
    }

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
