<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">@include('documentos._estilos')
<style> body { font-size: 11px; line-height: 1.45; } h1 { font-size: 13px; margin: 6px 0 4px; } .sec { font-weight: bold; margin: 12px 0 4px; }
 .linea-monto td { border-bottom: 1px dotted #999; } .total td { border-top: 1px solid #333; font-weight: bold; } </style>
</head>
<body>
@php $m = fn ($x) => 'S/ ' . number_format($x, 2, '.', ','); @endphp

<h1>CERTIFICADO DE RENTAS Y RETENCIONES POR RENTAS DE QUINTA CATEGORÍA</h1>
<div style="text-align:center; font-weight:bold; margin-bottom:12px;">EJERCICIO {{ $anio }}</div>

<p class="cuerpo">
    <strong>{{ $empresa->razon_social }}</strong>, con RUC N.º {{ $empresa->ruc }}@if($empresa->direccion), domiciliada en {{ $empresa->direccion }}@endif,
    debidamente representada por <strong>{{ mb_strtoupper($firma['nombre']) }}</strong>, en calidad de {{ $firma['cargo'] }},
</p>
<div style="text-align:center; font-weight:bold; margin:6px 0;">CERTIFICA</div>
<p class="cuerpo">
    Que {{ $trat['don'] }} <strong>{{ $nombre }}</strong>, identificado(a) con DNI N.º {{ $e->dni }}, quien se desempeñó
    como {{ $e->cargo }}, percibió rentas de quinta categoría durante el ejercicio gravable {{ $anio }}, por las cuales
    se efectuaron retenciones netas del Impuesto a la Renta por <strong>{{ $m($retenido) }}</strong>, conforme al siguiente detalle:
</p>

<div class="sec">1. RENTAS BRUTAS</div>
<table class="linea-monto">
    <tr><td>Sueldos y salarios</td><td class="der">{{ $m($v['sueldos']) }}</td></tr>
    <tr><td>Comisiones</td><td class="der">{{ $m($v['comisiones']) }}</td></tr>
    <tr><td>Participación en las utilidades</td><td class="der">{{ $m($v['utilidades']) }}</td></tr>
    <tr><td>Gratificación</td><td class="der">{{ $m($v['gratificacion']) }}</td></tr>
    <tr><td>Bonificaciones extraordinarias</td><td class="der">{{ $m($v['bonificaciones']) }}</td></tr>
    <tr><td>Remuneración vacacional</td><td class="der">{{ $m($v['remu_vacacional']) }}</td></tr>
    <tr><td>Vacaciones truncas</td><td class="der">{{ $m($v['vac_truncas']) }}</td></tr>
    <tr><td>Gratificación trunca</td><td class="der">{{ $m($v['grat_trunca']) }}</td></tr>
    <tr class="total"><td>RENTA BRUTA TOTAL INFORMADA</td><td class="der">{{ $m($rentaBruta) }}</td></tr>
</table>

<div class="sec">2. DEDUCCIONES DE LA RENTA DE 5.ª CATEGORÍA</div>
<table class="linea-monto">
    <tr><td>7 UIT (UIT {{ $m($uit) }})</td><td class="der">{{ $m($v['deduccion']) }}</td></tr>
</table>

<div class="sec">3. RENTA NETA</div>
<table class="linea-monto"><tr><td>Renta neta anual</td><td class="der">{{ $m($rentaNeta) }}</td></tr></table>

<div class="sec">4. IMPUESTO A LA RENTA</div>
<table class="linea-monto"><tr><td>Impuesto anual calculado</td><td class="der">{{ $m($impuesto) }}</td></tr></table>

<div class="sec">5. CRÉDITOS CONTRA EL IMPUESTO</div>
<table class="linea-monto">
    <tr><td>Retenciones de quinta categoría informadas</td><td class="der">{{ $m($v['retenciones']) }}</td></tr>
    <tr><td>Devolución de quinta categoría informada</td><td class="der">{{ $m($v['devolucion']) }}</td></tr>
</table>

<div class="sec">6. IMPUESTO TOTAL RETENIDO</div>
<table class="linea-monto"><tr><td>Retenciones netas considerando la devolución informada</td><td class="der">{{ $m($retenido) }}</td></tr></table>

<p style="margin-top:22px;">{{ $ciudad }}, {{ $emision }}</p>

<div class="firma" style="margin-top:60px;">
    <div class="linea"></div>
    {{ mb_strtoupper($firma['nombre']) }}<br>
    {{ $empresa->razon_social }}<br>
    {{ $firma['cargo'] }}
</div>
</body></html>
