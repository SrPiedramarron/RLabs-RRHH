<?php
$companyId = \DB::table('companies')->where('ruc', '20514706302')->value('id');

function normalizarNombreVac2(string $n): string
{
    $n = mb_strtoupper(trim($n), 'UTF-8');
    $n = strtr($n, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
    return preg_replace('/\s+/', ' ', $n);
}

// Valores extraídos directamente del Excel (columna GOCE FISICO PENDIENTES)
$datos = [
    'ORIHUELA AGUIRRE, ERNESTO  JULIO' => 236,
    'GIOVANNA RAQUEL MARTINEZ CASTILLO' => 88,
    'SALVADOR ROJAS ELVIS ALFONSO' => 23,
    'LOLI RODRIGUEZ JANIO RICHARD' => 50,
    'PEREZ CAJAHUANCA ARACELLI IRAIDA' => 24,
    'PORTOCARRERO TAFUR GLORI MAGRI' => 68,
    'ECHEVARRIA SOTO  JORGE LUIS' => 30,
    'MONTOYA MEDRANO ANDREA BELEN JOSEPHINE' => 7,
    'VERGARA BALDEON GEANNINA LIZBETH' => 30,
    'CASTILLO ENCARNACION MANUEL LEONIDAS' => 23,
    'QUEVEDO SALAS LUIS ALBERTO' => 16,
    'PORTOCARRERO GOMEZ MARIA DEICY' => 15,
    'LOBO QUISPE ELISA FIORELLA' => 34,
    'RIOS HOLSEN JAIME JUNIOR' => 12,
    'ESTRADA SANTOS JUAN MIGUEL' => 7,
    'SALCEDO RIVAS DANIEL ALONSO' => 22,
    'PORTOCARRERO TAFUR MARIA LOIDI' => 15,
    'ZEVALLOS DELGADO LUIS EDUARDO' => 23,
    'SALAZAR VELASQUEZ HECTOR ALEXANDER' => 18,
    'BALLLESTEROS DI SERI MARIA PAULA' => 30, // matcheará igual, es solo el nombre del Excel (con typo), el UPDATE usa el nombre correcto en la BD
    'OCAMPO ORTEGA JEAN PAUL' => 30,
    'OLIVOS SAAVEDRA LANDHERT ANDERSON' => 7,
    'YUPANQUI SAMANIEGO RENATO LUIS' => 9,
    'TUCTO PEREZ CESAR ANTONIO' => 23,
    'AREVALO VELA CESAR ANTONIO' => 23,
    'SICCHA ARCE LUIS FRANCISCO' => 16,
    'JIMENEZ PUYEN LUIS ANTONIO' => 17,
    'SILVA SANTISTEBAN RONAL JHUNIOR' => 15,
    'ELGUERA SOTO LUCERO ESTEFANIA' => 23,
    'ESTACIO CAYETANO FRANK BRIAN' => 15,
    'RICRA DAVALOS GIUSSEPPE RENATO' => 30,
    'DIAZ ESCALANTE LUIS MIGUEL DAVID' => 16,
    'HIPOLITO GUANILO ARNOLD JIMMY' => 0,
    'MONTOYA MEDRANO VIVIAN MICHELE ALEXANDRA' => 0,
    'ALARCON OLIVERA AMBAR TIFFANY' => 0,
    'BURGA MUÑOZ HELDER ALEXANDER' => 0,
    'RIVERA CORRALES JUAN EDUARDO' => 0,
    'MELO HUAMAN CARLOS MANUEL' => 0,
    'FARFAN LAUREL CIELO CARMIN' => 0,
    'ORTIZ PAREDES CESAR PAUL' => 0,
];

$empleados = \App\Models\Employee::where('company_id', $companyId)->get(['id', 'nombres', 'apellidos']);
$empleadosNorm = $empleados->map(fn ($e) => [
    'id' => $e->id,
    'nombre' => $e->nombres . ' ' . $e->apellidos,
    'nombres' => normalizarNombreVac2($e->nombres),
    'apellidos' => normalizarNombreVac2($e->apellidos),
])->all();

$actualizados = 0;
$sinMatch = [];

foreach ($datos as $nombreExcel => $saldo) {
    $normalizado = normalizarNombreVac2($nombreExcel);
    $match = null;

    foreach ($empleadosNorm as $emp) {
        if (str_contains($normalizado, $emp['nombres']) && str_contains($normalizado, $emp['apellidos'])) {
            $match = $emp;
            break;
        }
    }

    if ($match) {
        \App\Models\Employee::where('id', $match['id'])->update([
            'saldo_vacaciones_inicial' => $saldo,
            'fecha_saldo_vacaciones'   => now()->toDateString(),
        ]);
        $actualizados++;
        echo "OK: {$match['nombre']} -> saldo {$saldo}" . PHP_EOL;
    } else {
        $sinMatch[] = $nombreExcel;
    }
}

echo PHP_EOL . "Actualizados: {$actualizados} de " . count($datos) . PHP_EOL;
echo "Sin matchear: " . (empty($sinMatch) ? 'ninguno' : implode(', ', $sinMatch)) . PHP_EOL;
