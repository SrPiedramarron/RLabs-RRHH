<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
    /* Papel membretado: 3.5 cm de encabezado; el resto se compacta para que todo entre en una hoja. */
    @page { margin: 4.2cm 44px 22px 44px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; line-height: 1.22; }
    h1 { font-size: 12.5px; text-align: center; margin: 0; }
    .sub { text-align: center; font-size: 8.5px; margin: 1px 0 6px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 4px; vertical-align: top; }
    .der { text-align: right; white-space: nowrap; }
    .sec { font-weight: bold; margin: 6px 0 2px; }
    .cab td { padding: 0 4px; }
    .total td { border-top: 1px solid #333; font-weight: bold; }
    .gran td { border-top: 2px solid #111; font-weight: bold; font-size: 10.5px; padding-top: 3px; }
    .firmas { margin-top: 26px; page-break-inside: avoid; }
    .linea { border-top: 1px solid #333; width: 85%; margin: 0 auto 2px; }
    .caja-huella { border: 1px solid #333; width: 60px; height: 56px; margin: 0 auto 2px; }
</style>
</head>
<body>
@php
    $m  = fn ($x) => number_format((float) $x, 2, '.', ',');
    $mm = fn ($x) => ((float) $x) == 0 ? '-' : number_format((float) $x, 2, '.', ',');
    $MES = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',7=>'JULIO',8=>'AGOSTO',9=>'SETIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    $fd = fn (\Carbon\Carbon $f) => $f->format('d/m/Y');
    $cese = $l->fecha_cese;
    $ingreso = $e->fecha_ingreso ?? $cese;

    // Semestre de gratificación y de CTS en el que cae el cese
    $iniGrat = $cese->month <= 6 ? \Carbon\Carbon::create($cese->year, 1, 1) : \Carbon\Carbon::create($cese->year, 7, 1);
    $iniGrat = $iniGrat->lt($ingreso) ? $ingreso->copy() : $iniGrat;
    if (in_array($cese->month, [11, 12, 1, 2, 3, 4], true)) {
        $iniCts = $cese->month >= 11 ? \Carbon\Carbon::create($cese->year, 11, 1) : \Carbon\Carbon::create($cese->year - 1, 11, 1);
    } else {
        $iniCts = \Carbon\Carbon::create($cese->year, 5, 1);
    }
    $iniCts = $iniCts->lt($ingreso) ? $ingreso->copy() : $iniCts;

    $remCts   = (float) ($l->remuneracion_computable_cts ?? 0);
    $sexto    = (float) ($l->sexto_gratificacion_cts ?? 0);
    $promVar  = max(0, round($remCts - (float) $l->sueldo_base - (float) $l->asignacion_familiar - $sexto, 2));
    if ($remCts <= 0) { // liquidaciones calculadas antes de guardar las bases
        $remCts = (float) $l->meses_cts_trunca > 0 ? round($l->monto_cts_trunca * 12 / $l->meses_cts_trunca, 2) : (float) $l->sueldo_base + (float) $l->asignacion_familiar;
        $sexto  = max(0, round($remCts - (float) $l->sueldo_base - (float) $l->asignacion_familiar, 2));
        $promVar = 0;
    }
    $remGrat = (float) ($l->remuneracion_computable_gratificacion ?? 0);
    if ($remGrat <= 0) {
        $remGrat = (float) $l->meses_gratificacion_trunca > 0 ? round($l->monto_gratificacion_trunca * 6 / $l->meses_gratificacion_trunca, 2) : (float) $l->sueldo_base + (float) $l->asignacion_familiar;
    }
    $baseVac = (float) ($l->base_vacacional ?? 0);
    if ($baseVac <= 0) { $baseVac = (float) $l->sueldo_base + (float) $l->asignacion_familiar; }
    $mesesVac = (float) ($l->meses_vacaciones_truncas ?? ($l->dias_vacaciones_truncas / 30));
    $baseAfp  = round((float) $l->monto_vacaciones_truncas + (float) $l->remuneracion_vacacional_pendiente, 2);
    $tasaAfp  = $baseAfp > 0 ? round($l->descuento_afp_vacaciones / $baseAfp * 100, 2) : 0;
    $totalGrat = round((float) $l->monto_gratificacion_trunca + (float) $l->bonificacion_extraordinaria_trunca, 2);
@endphp

<h1>LIQUIDACIÓN DE BENEFICIOS SOCIALES</h1>
<div class="sub">D. Leg. N.º 650, D. Leg. 857 y D.S. N.º 001-97-TR</div>
<div style="text-align:center; font-weight:bold; margin-bottom:4px;">{{ $empresa->razon_social }}</div>

<table class="cab">
    <tr><td style="width:36%;">NOMBRE DEL TRABAJADOR</td><td>: {{ $nombre }}</td></tr>
    <tr><td>CARGO</td><td>: {{ mb_strtoupper($e->cargo ?? '') }}</td></tr>
    <tr><td>DNI</td><td>: {{ $e->dni }}</td></tr>
    <tr><td>FECHA DE INGRESO</td><td>: {{ $fd($ingreso) }}</td></tr>
    <tr><td>FECHA DE CESE</td><td>: {{ $fd($cese) }}</td></tr>
    <tr><td>SISTEMA DE PENSIONES</td><td>: {{ $afp }}</td></tr>
    <tr><td>PERIODO DE TRABAJO</td><td>: {{ $tiempo }}</td></tr>
    <tr><td>MOTIVO</td><td>: {{ mb_strtoupper($l->motivo_label) }}</td></tr>
</table>

<div class="sec">1.- REMUNERACIÓN COMPUTABLE</div>
<table>
    <tr><td>SUELDO BÁSICO</td><td class="der">{{ $m($l->sueldo_base) }}</td></tr>
    <tr><td>ASIGNACIÓN FAMILIAR</td><td class="der">{{ $mm($l->asignacion_familiar) }}</td></tr>
    @if($promVar > 0)<tr><td>PROMEDIO DE COMISIONES / HORAS EXTRA</td><td class="der">{{ $m($promVar) }}</td></tr>@endif
    <tr><td>PROMEDIO DE GRATIFICACIÓN PERCIBIDA (1/6)</td><td class="der">{{ $mm($sexto) }}</td></tr>
    <tr class="total"><td class="der">TOTAL S/</td><td class="der">{{ $m($remCts) }}</td></tr>
</table>

<div class="sec">2.- CÁLCULO DE LA CTS</div>
<table>
    <tr><td>PERIODO: {{ $fd($iniCts) }} AL {{ $fd($cese) }}</td><td></td></tr>
    <tr><td>{{ $m($remCts) }} ÷ 12 × {{ rtrim(rtrim(number_format((float) $l->meses_cts_trunca, 4), '0'), '.') }} MESES</td><td class="der">{{ $m($l->monto_cts_trunca) }}</td></tr>
    <tr class="total"><td class="der">TOTAL CTS POR PAGAR S/</td><td class="der">{{ $m($l->monto_cts_trunca) }}</td></tr>
</table>

<div class="sec">3.- CÁLCULO DE LA GRATIFICACIÓN</div>
<table>
    <tr><td>PERIODO: {{ $fd($iniGrat) }} AL {{ $fd($cese) }}</td><td></td></tr>
    <tr><td>{{ $m($remGrat) }} ÷ 6 × {{ rtrim(rtrim(number_format((float) $l->meses_gratificacion_trunca, 4), '0'), '.') }} MESES</td><td class="der">{{ $m($l->monto_gratificacion_trunca) }}</td></tr>
    <tr><td>BONIFICACIÓN EXTRAORDINARIA 9%</td><td class="der">{{ $m($l->bonificacion_extraordinaria_trunca) }}</td></tr>
    <tr class="total"><td class="der">TOTAL GRATIFICACIÓN POR PAGAR S/</td><td class="der">{{ $m($totalGrat) }}</td></tr>
</table>

<div class="sec">4.- VACACIONES</div>
<table>
    <tr><td>Vacaciones truncas: {{ $m($baseVac) }} ÷ 12 × {{ rtrim(rtrim(number_format($mesesVac, 4), '0'), '.') }} MESES</td><td class="der">{{ $m($l->monto_vacaciones_truncas) }}</td></tr>
    @if((float) $l->remuneracion_vacacional_pendiente > 0)
    <tr><td>Vacaciones no gozadas (remuneración vacacional): 30 DÍAS</td><td class="der">{{ $m($l->remuneracion_vacacional_pendiente) }}</td></tr>
    @endif
    @if((float) $l->indemnizacion_vacacional > 0)
    <tr><td>Indemnización vacacional</td><td class="der">{{ $m($l->indemnizacion_vacacional) }}</td></tr>
    @endif
    <tr><td>DESCUENTO {{ $afp }} {{ $tasaAfp }}% × {{ $m($baseAfp) }}</td><td class="der">-{{ $m($l->descuento_afp_vacaciones) }}</td></tr>
    <tr class="total"><td class="der">TOTAL VACACIONES POR PAGAR S/</td><td class="der">{{ $m($l->total_vacaciones_por_pagar) }}</td></tr>
    <tr style="font-style:italic; color:#444;"><td>Aportación EsSalud 9% × {{ $m($baseAfp) }} (a cargo del empleador; no se descuenta al trabajador)</td><td class="der">{{ $m($l->aporte_essalud_vacaciones) }}</td></tr>
</table>

@if((float) $l->indemnizacion > 0)
<div class="sec">5.- INDEMNIZACIÓN POR DESPIDO</div>
<table>
    <tr class="total"><td class="der">TOTAL INDEMNIZACIÓN S/</td><td class="der">{{ $m($l->indemnizacion) }}</td></tr>
</table>
@endif

@if($renta5ta > 0 || $devolucion > 0)
<div class="sec">6.- RENTA 5TA CATEGORÍA</div>
<table>
    @if($renta5ta > 0)<tr><td>RETENCIÓN RENTA 5TA CATEGORÍA</td><td class="der">-{{ $m($renta5ta) }}</td></tr>@endif
    @if($devolucion > 0)<tr><td>DEVOLUCIÓN RENTA 5TA CATEGORÍA</td><td class="der">{{ $m($devolucion) }}</td></tr>@endif
</table>
@endif

<table style="margin-top:6px;">
    <tr class="gran"><td>TOTAL A RECIBIR POR CONCEPTO DE LIQUIDACIÓN</td><td class="der">S/ {{ $m($total) }}</td></tr>
</table>

<p style="text-align:justify; margin: 8px 0 4px;">
    RECIBÍ DE {{ mb_strtoupper($empresa->razon_social) }} LA SUMA DE S/ {{ $m($total) }} ({{ $letras }}) POR MI TIEMPO DE SERVICIO EN LA
    EMPRESA, POR LO QUE HABIÉNDOSE CALCULADO LA PRESENTE LIQUIDACIÓN DE BENEFICIOS SOCIALES DE ACUERDO A LAS DISPOSICIONES LEGALES
    VIGENTES, FIRMO EN SEÑAL DE CONFORMIDAD Y DECLARO NO TENER NADA QUE RECLAMAR EN EL FUTURO POR ESTE NI POR OTRO CONCEPTO.
</p>

<p style="margin: 4px 0;">{{ $ciudad }}, {{ $emision }}</p>

<table class="firmas">
    <tr>
        <td style="width:34%; text-align:center;"><div class="linea"></div>APROBADO:<br>{{ $firma['cargo'] }}</td>
        <td style="width:40%; text-align:center;"><div class="linea"></div>{{ $nombre }}<br>FIRMA DEL EX-TRABAJADOR<br>D.N.I. {{ $e->dni }}</td>
        <td style="width:26%; text-align:center;"><div class="caja-huella"></div>HUELLA DIGITAL</td>
    </tr>
</table>
</body></html>
