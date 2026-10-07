<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 40px 48px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    .pagina { page-break-after: always; }
    .pagina:last-child { page-break-after: auto; }
    .centro { text-align: center; }
    .empresa { font-size: 15px; font-weight: bold; }
    .titulo { font-size: 13px; font-weight: bold; margin-top: 18px; }
    .constancia { font-size: 14px; font-weight: bold; margin: 16px 0 8px; letter-spacing: 3px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 3px 4px; vertical-align: top; }
    .der { text-align: right; }
    .etq { font-weight: bold; }
    .caja { border: 1px solid #333; padding: 8px 10px; margin-top: 14px; }
    .sub { font-weight: bold; text-decoration: underline; margin-bottom: 4px; }
    .total td { border-top: 1px solid #333; font-weight: bold; }
    .firmas { margin-top: 90px; }
    .firma { text-align: center; width: 50%; }
    .firma .linea { border-top: 1px solid #333; width: 210px; margin: 0 auto 4px; }
</style>
</head>
<body>
@foreach($filas as $f)
@php
    $m = fn ($v) => number_format($v, 2, '.', ',');
    $d = $f['d'];
@endphp
<div class="pagina">
    <div class="centro empresa">{{ $f['empresa'] }}</div>
    <div class="centro">RUC {{ $f['ruc'] }}</div>

    <div class="centro titulo">COMPENSACIÓN POR TIEMPO DE SERVICIOS</div>
    <div class="centro constancia">CONSTANCIA</div>
    <div class="centro">DEPÓSITO POR BANCOS &nbsp; MONEDA: SOLES</div>
    @if($f['banco'])
        <div class="centro">ENTIDAD: {{ strtoupper($f['banco']) }}</div>
    @endif

    <div class="caja">
        <table>
            <tr>
                <td style="width:55%;">
                    <div class="sub">DATOS GENERALES</div>
                    <table>
                        <tr><td class="etq">Apellidos y nombres</td><td>: {{ $f['empleado'] }}</td></tr>
                        <tr><td class="etq">Fecha de ingreso</td><td>: {{ $f['ingreso'] }}</td></tr>
                        <tr><td class="etq">Área</td><td>: {{ $f['area'] }}</td></tr>
                        <tr><td class="etq">Puesto</td><td>: {{ $f['puesto'] }}</td></tr>
                        <tr><td class="etq">Periodo que se liquida</td><td>: {{ $f['desde'] }} AL {{ $f['hasta'] }}</td></tr>
                        @if($f['deposito'])
                        <tr><td class="etq">Fecha de depósito</td><td>: {{ $f['deposito'] }}</td></tr>
                        @endif
                        <tr><td class="etq">Tiempo computado</td><td>: Años: 0 &nbsp; Meses: {{ str_pad($f['meses'], 2, '0', STR_PAD_LEFT) }} &nbsp; Días: {{ str_pad($f['dias'], 2, '0', STR_PAD_LEFT) }}</td></tr>
                    </table>
                </td>
                <td style="width:45%;">
                    <div class="sub">REMUNERACIÓN COMPUTABLE</div>
                    <table>
                        <tr><td>SUELDO BÁSICO</td><td class="der">{{ $m($d->sueldo_base) }}</td></tr>
                        <tr><td>ASIGNACIÓN FAMILIAR</td><td class="der">{{ $m($d->asignacion_familiar) }}</td></tr>
                        @if($d->promedio_comisiones > 0)
                        <tr><td>PROMEDIO COMISIONES</td><td class="der">{{ $m($d->promedio_comisiones) }}</td></tr>
                        @endif
                        @if($d->promedio_horas_extra > 0)
                        <tr><td>PROMEDIO HORAS EXTRA</td><td class="der">{{ $m($d->promedio_horas_extra) }}</td></tr>
                        @endif
                        @if(($d->promedio_bonos ?? 0) > 0)
                        <tr><td>PROMEDIO BONOS</td><td class="der">{{ $m($d->promedio_bonos) }}</td></tr>
                        @endif
                        <tr><td>1/6 ÚLTIMA GRATIF.</td><td class="der">{{ $m($d->sexto_gratificacion) }}</td></tr>
                        <tr class="total"><td>Total Rem. Computable S/</td><td class="der">{{ $m($f['rem']) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="caja">
        <div class="sub">LIQUIDACIÓN</div>
        <table>
            <tr>
                <td>1. Compensación por Tiempo de Servicios</td>
                <td></td>
                <td class="der"></td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;A. Por los años completos</td>
                <td class="der">0.00 × {{ $m($f['rem']) }} / 12</td>
                <td class="der">0.00</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;B. Por los meses completos</td>
                <td class="der">{{ number_format($f['meses'], 2) }} × {{ $m($f['rem']) }} / 12</td>
                <td class="der">{{ $m($f['por_meses']) }}</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;C. Por los días</td>
                <td class="der">{{ number_format($f['dias'], 2) }} × {{ $m($f['rem']) }} / 12 / 30</td>
                <td class="der">{{ $m($f['por_dias']) }}</td>
            </tr>
            <tr class="total">
                <td colspan="2">TOTAL CTS A DEPOSITAR &nbsp; S/</td>
                <td class="der">{{ $m($f['total']) }}</td>
            </tr>
        </table>
    </div>

    <p style="margin-top:18px;">
        @if($f['banco'] && $f['cuenta'])
            Realizado en {{ $f['banco'] }}, en la cuenta N.º {{ $f['cuenta'] }}.
        @endif
    </p>

    <p style="text-align: justify; line-height: 1.5;">
        La empresa otorga al Sr(a). {{ $f['empleado'] }} la presente Constancia de Depósito de su
        Compensación por Tiempo de Servicios (CTS), correspondiente al periodo desde
        {{ $f['desde'] }} al {{ $f['hasta'] }}, por el monto de S/ {{ $m($f['total']) }} soles.
    </p>

    <table class="firmas">
        <tr>
            <td class="firma">
                <div class="linea"></div>
                {{ $f['rep_nombre'] ?: $f['empresa'] }}<br>
                <span style="font-size:10px; color:#555;">{{ $f['rep_cargo'] }} — {{ $f['empresa'] }}</span>
            </td>
            <td class="firma">
                <div class="linea"></div>
                {{ $f['empleado'] }}<br>
                <span style="font-size:10px; color:#555;">Trabajador</span>
            </td>
        </tr>
    </table>
</div>
@endforeach
</body>
</html>
