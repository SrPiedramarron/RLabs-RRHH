<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 5px; }
        .header { text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 6px; }
        .box { border: 1px solid #333; padding: 6px; margin-bottom: 6px; }
        .label { font-size: 8px; color: #555; }
        .concepto-table th { background: #1f4e79; color: #fff; text-align: left; }
        .concepto-table td, .concepto-table th { border: 1px solid #999; }
        .right { text-align: right; }
        .total-row { font-weight: bold; background: #ddeeff; }
    </style>
</head>
<body>
    <div class="header">
        PDT Planilla Electrónica - PLAME<br>
        RUC: {{ $d['ruc'] }} — {{ $d['empleador'] }}<br>
        Periodo: {{ $d['periodo'] }}
    </div>

    <div class="box">
        <table>
            <tr>
                <td><span class="label">DNI</span><br>{{ $d['dni'] }}</td>
                <td><span class="label">Nombres y Apellidos</span><br>{{ $d['nombres_completos'] }}</td>
                <td><span class="label">Situación</span><br>{{ $d['situacion'] }}</td>
            </tr>
            <tr>
                <td><span class="label">Fecha de Ingreso</span><br>{{ $d['fecha_ingreso'] }}</td>
                <td><span class="label">Régimen Pensionario</span><br>{{ $d['regimen_pensionario'] }}</td>
                <td><span class="label">CUSPP</span><br>{{ $d['cuspp'] ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table>
            <tr>
                <td><span class="label">Días Laborados</span><br>{{ $d['dias_laborados'] }}</td>
                <td><span class="label">Días No Laborados</span><br>{{ $d['dias_no_laborados'] }}</td>
                <td><span class="label">Condición</span><br>{{ $d['condicion'] }}</td>
                <td><span class="label">Sobretiempo</span><br>{{ sprintf('%02d:%02d', $d['sobretiempo_horas'], $d['sobretiempo_minutos']) }}</td>
            </tr>
        </table>
    </div>

    <table class="concepto-table">
        <tr><th colspan="3">Ingresos</th></tr>
        @foreach($d['ingresos'] as $codigo => [$concepto, $monto])
            <tr>
                <td style="width:40px">{{ $codigo }}</td>
                <td>{{ $concepto }}</td>
                <td class="right" style="width:80px">S/ {{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach

        <tr><th colspan="3">Descuentos</th></tr>
        @foreach($d['descuentos'] as $codigo => [$concepto, $monto])
            <tr>
                <td>{{ $codigo }}</td>
                <td>{{ $concepto }}</td>
                <td class="right">S/ {{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach

        <tr><th colspan="3">Aportes del Trabajador</th></tr>
        @foreach($d['aportes_trabajador'] as $codigo => [$concepto, $monto])
            <tr>
                <td>{{ $codigo }}</td>
                <td>{{ $concepto }}</td>
                <td class="right">S/ {{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach

        <tr class="total-row">
            <td colspan="2">NETO A PAGAR</td>
            <td class="right">S/ {{ number_format($d['neto_pagar'], 2) }}</td>
        </tr>

        <tr><th colspan="3">Aportes del Empleador (informativo)</th></tr>
        @foreach($d['aportes_empleador'] as $codigo => [$concepto, $monto])
            <tr>
                <td>{{ $codigo }}</td>
                <td>{{ $concepto }}</td>
                <td class="right">S/ {{ number_format($monto, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <br><br>
    <table>
        <tr>
            <td style="width:50%; text-align:center; border-top: 1px solid #333;">FIRMA DEL TRABAJADOR</td>
            <td style="width:50%; text-align:center; border-top: 1px solid #333;">FIRMA DEL EMPLEADOR</td>
        </tr>
    </table>
</body>
</html>
