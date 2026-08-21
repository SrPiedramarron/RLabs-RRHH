<?php
$col1 = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM comision_uploads WHERE Field = 'estado'");
echo "comision_uploads.estado: " . $col1[0]->Type . PHP_EOL;

$col3 = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM comision_detalles WHERE Field = 'porcentaje_comision'");
echo "comision_detalles.porcentaje_comision: " . $col3[0]->Type . PHP_EOL;

$col2 = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM comision_detalles WHERE Field = 'estado'");
echo "comision_detalles.estado: " . $col2[0]->Type . PHP_EOL;