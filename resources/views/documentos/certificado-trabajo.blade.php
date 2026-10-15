<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">@include('documentos._estilos')</head>
<body>
<h1>{{ $cese ? 'CERTIFICADO DE TRABAJO' : 'CONSTANCIA DE TRABAJO' }}</h1>

<p class="cuerpo">
    Mediante el presente, dejamos constancia que {{ $trat['articulo'] }} {{ $trat['titulo'] }} <strong>{{ $nombre }}</strong>,
    identificado(a) con DNI N.º {{ $e->dni }},
    @if($cese)
        ha laborado en nuestra empresa desempeñando el cargo de <strong>{{ mb_strtoupper($e->cargo ?? '') }}</strong>
        desde el {{ $ingreso }} hasta el {{ $cese }}.
    @else
        viene laborando en nuestra empresa desempeñando el cargo de <strong>{{ mb_strtoupper($e->cargo ?? '') }}</strong>
        desde el {{ $ingreso }} hasta la fecha.
    @endif
</p>

<p class="cuerpo">Se extiende {{ $cese ? 'el presente certificado' : 'la presente constancia' }} para los fines que estime conveniente.</p>

<p class="der" style="margin-top:30px;">{{ $ciudad }}, {{ $emision }}</p>

<p style="margin-top:30px;">Atentamente,</p>

<div class="firma">
    <div class="linea"></div>
    {{ $firma['nombre'] }}<br>
    {{ $firma['cargo'] }}<br>
    {{ $empresa->razon_social }}
</div>
</body></html>
