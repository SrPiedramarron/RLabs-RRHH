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

    /** Lista de empleados normalizados, para matching parcial (contiene). */
    private array $empleadosNormalizados = [];

    /** Alias manuales para typos conocidos del Excel: "NOMBRE APELLIDO" => employee_id */
    private array $aliasManual = [];

    public function importarHoja(
        string $path,
        string $sheetName,
        int $companyId,
        int $anio,
        int $filaNombres = 4,
        int $filaApellidos = 5,
        int $colInicioEmpleados = 3, // C=3
        int $filaInicioDatos = 6,
        array $aliasManual = [], // "NOMBRE EXCEL COMO APARECE" => employee_id, para typos conocidos
    ): array {
        $this->construirListaEmpleados($companyId);
        $this->aliasManual = array_change_key_case($aliasManual, CASE_UPPER);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $spreadsheet = $reader->load($path);
        $ws          = $spreadsheet->getSheetByName($sheetName);

        if (!$ws) {
            return ['error' => "No se encontró la hoja '{$sheetName}' en el archivo."];
        }

        $columnasEmpleado = [];
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

            [$employeeId, $estado] = $this->buscarEmpleadoPorNombreCorto($nombre, $apellido);

            // Alias manual tiene prioridad (para typos conocidos del Excel)
            $claveAlias = strtoupper(trim($nombreCompleto));
            if (isset($this->aliasManual[$claveAlias])) {
                $employeeId = $this->aliasManual[$claveAlias];
                $estado     = 'ok_alias';
            }

            $columnasEmpleado[$col] = [
                'nombre_original' => $nombreCompleto,
                'employee_id'     => $employeeId,
                'estado_match'    => $estado, // 'ok' | 'sin_match' | 'ambiguo'
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
                    'estado_match' => $info['estado_match'],
                ];
            }
        }

        $sinMatch = collect($columnasEmpleado)
            ->filter(fn ($i) => $i['estado_match'] === 'sin_match')
            ->pluck('nombre_original')
            ->unique()
            ->values()
            ->toArray();

        $ambiguos = collect($columnasEmpleado)
            ->filter(fn ($i) => $i['estado_match'] === 'ambiguo')
            ->pluck('nombre_original')
            ->unique()
            ->values()
            ->toArray();

        return [
            'hoja'              => $sheetName,
            'empleados_en_hoja' => count($columnasEmpleado),
            'empleados_sin_matchear' => $sinMatch,
            'empleados_ambiguos'     => $ambiguos,
            'filas_importadas'  => $importados,
            'detalle'           => $lineasProcesadas,
        ];
    }

    /**
     * Importa la sección "RETENCIONES" (lo que YA se retuvo mes a mes),
     * distinta de la sección de ingresos. Estructura más simple: una fila
     * por mes directamente (sin sub-filas de concepto), empezando después
     * de la etiqueta "RETENCIONES" y terminando en "RETENCIONES APLICADAS".
     * Ignora filas intermedias que no sean nombre de mes (ej. "UTILIDADES"
     * insertada como separador en el Excel real de InProcess).
     */
    public function importarRetenciones(
        string $path,
        string $sheetName,
        int $companyId,
        int $anio,
        int $filaNombres = 4,
        int $filaApellidos = 5,
        int $colInicioEmpleados = 3,
        array $aliasManual = [],
    ): array {
        $this->construirListaEmpleados($companyId);
        $this->aliasManual = array_change_key_case($aliasManual, CASE_UPPER);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $spreadsheet = $reader->load($path);
        $ws          = $spreadsheet->getSheetByName($sheetName);

        if (!$ws) {
            return ['error' => "No se encontró la hoja '{$sheetName}'."];
        }

        $columnasEmpleado = [];
        $highestCol = $ws->getHighestDataColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($col = $colInicioEmpleados; $col <= $highestColIndex; $col++) {
            $nombre   = trim((string) $ws->getCellByColumnAndRow($col, $filaNombres)->getValue());
            $apellido = trim((string) $ws->getCellByColumnAndRow($col, $filaApellidos)->getValue());

            if (strtoupper($nombre) === 'TOTALES' || (empty($nombre) && empty($apellido))) {
                continue;
            }

            $nombreCompleto = trim($nombre . ' ' . $apellido);
            [$employeeId, $estado] = $this->buscarEmpleadoPorNombreCorto($nombre, $apellido);

            $claveAlias = strtoupper($nombreCompleto);
            if (isset($this->aliasManual[$claveAlias])) {
                $employeeId = $this->aliasManual[$claveAlias];
                $estado     = 'ok_alias';
            }

            $columnasEmpleado[$col] = [
                'nombre_original' => $nombreCompleto,
                'employee_id'     => $employeeId,
                'estado_match'    => $estado,
            ];
        }

        // Buscar la fila donde dice "RETENCIONES" (marca de inicio)
        $highestRow  = $ws->getHighestDataRow();
        $filaInicio  = null;
        for ($row = 1; $row <= $highestRow; $row++) {
            $val = trim((string) $ws->getCellByColumnAndRow(2, $row)->getValue());
            if (strtoupper($val) === 'RETENCIONES') {
                $filaInicio = $row + 1;
                break;
            }
        }

        if (!$filaInicio) {
            return ['error' => "No se encontró la sección 'RETENCIONES' en la hoja."];
        }

        $importados = 0;
        $lineasProcesadas = [];

        for ($row = $filaInicio; $row <= $highestRow; $row++) {
            $etiqueta = trim((string) $ws->getCellByColumnAndRow(2, $row)->getValue());

            if (strtoupper($etiqueta) === 'RETENCIONES APLICADAS' || empty($etiqueta) && $row > $filaInicio + 15) {
                break; // fin de la sección
            }

            $mes = self::MESES[strtoupper($etiqueta)] ?? null;
            if (!$mes) {
                continue; // fila ruido (ej. "UTILIDADES" insertada), se ignora
            }

            $periodo = "{$anio}-{$mes}";

            foreach ($columnasEmpleado as $col => $info) {
                $valor = $ws->getCellByColumnAndRow($col, $row)->getValue();
                $monto = is_numeric($valor) ? (float) $valor : 0.0;

                if ($monto <= 0 || !$info['employee_id']) {
                    if ($monto > 0 && !$info['employee_id']) {
                        $lineasProcesadas[] = [
                            'periodo' => $periodo, 'empleado' => $info['nombre_original'],
                            'monto' => $monto, 'matched' => false,
                        ];
                    }
                    continue;
                }

                IngresoHistorico5ta::updateOrCreate(
                    [
                        'employee_id' => $info['employee_id'],
                        'periodo'     => $periodo,
                        'concepto'    => 'RETENCION_5TA_APLICADA',
                    ],
                    ['company_id' => $companyId, 'monto' => $monto, 'fuente' => 'import_excel_retenciones_' . $sheetName]
                );
                $importados++;

                $lineasProcesadas[] = [
                    'periodo' => $periodo, 'empleado' => $info['nombre_original'],
                    'monto' => $monto, 'matched' => true,
                ];
            }
        }

        return [
            'hoja' => $sheetName,
            'filas_importadas' => $importados,
            'detalle' => $lineasProcesadas,
        ];
    }

    private function construirListaEmpleados(int $companyId): void
    {
        $this->empleadosNormalizados = Employee::where('company_id', $companyId)
            ->get(['id', 'nombres', 'apellidos'])
            ->map(fn ($e) => [
                'id'        => $e->id,
                'nombres'   => $this->normalizarNombre($e->nombres),
                'apellidos' => $this->normalizarNombre($e->apellidos),
                'original'  => $e->nombres . ' ' . $e->apellidos,
            ])
            ->all();
    }

    /**
     * Matching PARCIAL: el primer nombre y primer apellido del Excel deben
     * estar CONTENIDOS dentro del nombre/apellido completo del sistema
     * (que puede tener segundo nombre y segundo apellido). Si hay más de
     * un empleado que calza, se reporta como 'ambiguo' — mejor pedir
     * confirmación manual que asignar mal un histórico de sueldos.
     */
    private function buscarEmpleadoPorNombreCorto(string $primerNombre, string $primerApellido): array
    {
        $pn = $this->normalizarNombre($primerNombre);
        $pa = $this->normalizarNombre($primerApellido);

        if (empty($pn) || empty($pa)) {
            return [null, 'sin_match'];
        }

        $coincidencias = array_filter(
            $this->empleadosNormalizados,
            fn ($e) => str_contains($e['nombres'], $pn) && str_contains($e['apellidos'], $pa)
        );

        if (count($coincidencias) === 1) {
            return [array_values($coincidencias)[0]['id'], 'ok'];
        }

        if (count($coincidencias) > 1) {
            return [null, 'ambiguo'];
        }

        return [null, 'sin_match'];
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
