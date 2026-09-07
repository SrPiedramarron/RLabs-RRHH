<?php
// Ejecutar con: php artisan tinker --execute="include 'importar_saldo_vacaciones.php';"
// Ajusta la ruta del Excel si está en otro lugar.

$companyId = \DB::table('companies')->where('ruc', '20514706302')->value('id'); // InProcess
$path = base_path('CONTROL_DE_VACACIONES_2026.xls');

if (!file_exists($path)) {
    echo "No se encontró el archivo en la raíz del proyecto." . PHP_EOL;
    return;
}

function normalizarNombreVac(string $n): string
{
    $n = mb_strtoupper(trim($n), 'UTF-8');
    $n = strtr($n, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
    return preg_replace('/\s+/', ' ', $n);
}

$empleados = \App\Models\Employee::where('company_id', $companyId)->get(['id', 'nombres', 'apellidos']);
$empleadosNorm = $empleados->map(fn ($e) => [
    'id' => $e->id,
    'nombres' => normalizarNombreVac($e->nombres),
    'apellidos' => normalizarNombreVac($e->apellidos),
])->all();

// Lectura del Excel .xls (formato antiguo) — requiere PhpSpreadsheet con Xls reader
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly(['2026']);
$spreadsheet = $reader->load($path);
$ws = $spreadsheet->getSheetByName('2026');

$highestRow = $ws->getHighestDataRow();
$actualizados = 0;
$sinMatch = [];

for ($row = 6; $row <= $highestRow; $row++) {
    $nombreCompleto = trim((string) $ws->getCellByColumnAndRow(2, $row)->getCalculatedValue());
    if (empty($nombreCompleto)) {
        continue;
    }

    $goce = $ws->getCellByColumnAndRow(218, $row)->getCalculatedValue(); // GOCE FISICO PENDIENTES
    if (!is_numeric($goce)) {
        continue;
    }

    // Matching: probar como "nombres apellidos" completo contenido en el
    // registro (ya que el Excel trae "APELLIDOS, NOMBRES" o similar todo junto)
    $normalizado = normalizarNombreVac($nombreCompleto);

    $match = null;
    foreach ($empleadosNorm as $emp) {
        if (str_contains($normalizado, $emp['nombres']) && str_contains($normalizado, $emp['apellidos'])) {
            $match = $emp;
            break;
        }
    }

    if ($match) {
        \App\Models\Employee::where('id', $match['id'])->update([
            'saldo_vacaciones_inicial' => (float) $goce,
            'fecha_saldo_vacaciones'   => now()->toDateString(),
        ]);
        $actualizados++;
        echo "OK: {$nombreCompleto} -> saldo {$goce}" . PHP_EOL;
    } else {
        $sinMatch[] = $nombreCompleto;
    }
}

echo PHP_EOL . "Actualizados: {$actualizados}" . PHP_EOL;
echo "Sin matchear: " . (empty($sinMatch) ? 'ninguno' : implode(', ', $sinMatch)) . PHP_EOL;
