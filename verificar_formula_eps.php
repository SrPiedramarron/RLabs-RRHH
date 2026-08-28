<?php
// Prueba aislada de la fórmula EPS con el ejemplo exacto que dio RRHH:
// Sueldo 3000, EPS 268 con IGV -> esperado: crédito 67.50, empresa 47.89, trabajador 111.73

$sueldo = 3000;
$montoEpsConIgv = 268;

$essalud = round($sueldo * 0.09, 2); // asumiendo bruto = sueldo, sin faltas/tardanzas
$importeSinIgv = round($montoEpsConIgv / 1.18, 2);
$credito = round($essalud * 0.25, 2);
$neto = max(0, $importeSinIgv - $credito);
$empresa = round($neto * 0.30, 2);
$trabajador = round($neto * 0.70, 2);

echo "EsSalud: {$essalud} (esperado 270.00)" . PHP_EOL;
echo "Importe sin IGV: {$importeSinIgv} (esperado 227.12)" . PHP_EOL;
echo "Crédito EPS: {$credito} (esperado 67.50)" . PHP_EOL;
echo "Importe neto: {$neto} (esperado 159.62)" . PHP_EOL;
echo "Empresa (30%): {$empresa} (esperado 47.89)" . PHP_EOL;
echo "Trabajador (70%): {$trabajador} (esperado 111.73)" . PHP_EOL;
