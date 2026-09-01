<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use PhpOffice\PhpSpreadsheet\IOFactory;

class HistoricoRentaImportService
{
    private const MESES = [
        'ENERO' => '01', 'FEBRERO' => '02', 'MARZO' => '03', 'ABRIL' => '04',
        'MAYO' => '05', 'JUNIO' => '06', 'JULIO' => '07', 'AGOSTO' => '08',
        'SEPTIEMBRE' => '09', 'SETIEMBRE' => '09', 'OCTUBRE' => '10',
        'NOVIEMBRE' => '11', 'DICIEMBRE' => '12',
    ];

    /** Mapa normalizado nombre => employee_id, filtrado por empresa. */
    private array $mapaEmpleados = [];

    /**
     * Importa una hoja del Excel histórico. Devuelve un reporte detallado
     * para verificar visualmente antes de confiar en los datos.
     *
     * Formato esperado: fila 4 = nombres, fila 5 = apellidos (a partir de
     * la columna indicada), luego bloques por mes: una fila con el nombre
     * del mes, seguida de N filas de conceptos con montos por empleado,
     * terminando en una fila vacía antes del siguiente mes.
     */
    public function importarHoja(
        string $path,
        string $sheetName,
        int $companyId,
        int $anio,
        int $filaNombres = 4,
        int $filaApellidos = 5,
        int $colInicioEmpleados = 3, // C=3
        int $filaInicioDatos = 6,
    ): array {
        $this->construirMapaEmpleados($companyId);

        $spreadsheet = IOFactory::load($path);
        $ws          = $spreadsheet->getSheetByName($sheetName);

        if (!$ws) {
            return ['error' => "No se encontró la hoja '{$sheetName}' en el archivo."];
        }

        // Detectar columnas de empleados: desde $colInicioEmpleados hasta
        // encontrar la columna "TOTALES" (o una celda vacía sostenida).
        $columnasEmpleado = []; // colIndex => ['nombre_normalizado' => ..., 'employee_id' => ...|null, 'nombre_original' => ...]
        $highestCol = $ws->getHighestDataColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($col = $colInicioEmpleados; $col <= $highestColIndex; $col++) {
            $nombre    = trim((string) $ws->getCellByColumnAndRow($col, $filaNombres)->getValue());
            $apellido  = trim((string) $ws->getCellByColumnAndRow($col, $filaApellidos)->getValue());

            if (strtoupper($nombre) === 'TOTALES' || (empty($nombre) && empty($apellido))) {
                continue;
            }

            $nombreCompleto = trim($nombre . ' ' . $apellido);
            if (empty($nombreCompleto)) {
                continue;
            }

            $normalizado = $this->normalizarNombre($nombreCompleto);
            $columnasEmpleado[$col] = [
                'nombre_original' => $nombreCompleto,
                'normalizado'     => $normalizado,
                'employee_id'     => $this->mapaEmpleados[$normalizado] ?? null,
            ];
        }

        // Recorrer filas: detectar bloques de mes y filas de concepto.
        $importados      = 0;
        $lineasProcesadas = [];
        $mesActual        = null;
        $highestRow       = $ws->getHighestDataRow();

        for ($row = $filaInicioDatos; $row <= $highestRow; $row++) {
            $primeraCol = trim((string) $ws->getCellByColumnAndRow(2, $row)->getValue()); // col B

            if (empty($primeraCol)) {
                continue; // fila espaciadora entre meses
            }

            $mesDetectado = self::MESES[strtoupper($primeraCol)] ?? null;

            if ($mesDetectado) {
                $mesActual = $mesDetectado;
                continue; // esta fila es el título del mes, no tiene montos
            }

            if (!$mesActual) {
                continue; // no hemos visto ningún mes todavía, ignorar
            }

            // Esta fila es un CONCEPTO (ej. "SUELDO + ASIG FAM")
            $concepto = $primeraCol;
            $periodo  = "{$anio}-{$mesActual}";

            foreach ($columnasEmpleado as $col => $info) {
                $valor = $ws->getCellByColumnAndRow($col, $row)->getValue();
                $monto = is_numeric($valor) ? (float) $valor : 0.0;

                if ($monto <= 0) {
                    continue;
                }

                if ($info['employee_id']) {
                    IngresoHistorico5ta::updateOrCreate(
                        [
                            'employee_id' => $info['employee_id'],
                            'periodo'     => $periodo,
                            'concepto'    => $concepto,
                        ],
                        [
                            'company_id' => $companyId,
                            'monto'      => $monto,
                            'fuente'     => 'import_excel_' . $sheetName,
                        ]
                    );
                    $importados++;
                }

                $lineasProcesadas[] = [
                    'periodo'      => $periodo,
                    'concepto'     => $concepto,
                    'empleado'     => $info['nombre_original'],
                    'monto'        => $monto,
                    'matched'      => $info['employee_id'] !== null,
                ];
            }
        }

        $noMatcheados = collect($columnasEmpleado)
            ->filter(fn ($i) => $i['employee_id'] === null)
            ->pluck('nombre_original')
            ->unique()
            ->values()
            ->toArray();

        return [
            'hoja'              => $sheetName,
            'empleados_en_hoja' => count($columnasEmpleado),
            'empleados_sin_matchear' => $noMatcheados,
            'filas_importadas'  => $importados,
            'detalle'           => $lineasProcesadas,
        ];
    }

    private function construirMapaEmpleados(int $companyId): void
    {
        $this->mapaEmpleados = [];

        Employee::where('company_id', $companyId)->get(['id', 'nombres', 'apellidos'])->each(function ($e) {
            $orden1 = $this->normalizarNombre($e->nombres . ' ' . $e->apellidos);
            $orden2 = $this->normalizarNombre($e->apellidos . ' ' . $e->nombres);
            $this->mapaEmpleados[$orden1] = $e->id;
            $this->mapaEmpleados[$orden2] = $e->id;
        });
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
        return preg_replace('/\s+/', ' ', $nombre);
    }
}
