<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">@include('documentos._estilos')</head>
<body>
<h1>CERTIFICADO DE CESE</h1>

<p>{{ $ciudad }}, {{ $emision }}</p>

<p>Señores:<br><strong>{{ mb_strtoupper($banco ?: '—') }}</strong><br>Presente.-</p>

<p>De nuestra consideración:</p>

<p class="cuerpo">
    Por la presente certificamos que {{ $trat['articulo'] }} {{ $trat['titulo'] }} <strong>{{ $nombre }}</strong>,
    identificado(a) con DNI N.º {{ $e->dni }}, ha laborado en nuestra empresa hasta el {{ $cese }}.
</p>

<p class="cuerpo">
    En tal sentido y con arreglo a lo dispuesto en el Art. 45 del Decreto Legislativo N.º 650 – Ley de Compensación por
    Tiempo de Servicios, se le otorga la presente constancia de cese, a fin de que nuestro ex servidor pueda solicitar el
    retiro por su compensación por tiempo de servicios y sus intereses acumulados a la fecha, depositados en vuestra
    entidad bancaria según cuenta CTS N.º <strong>{{ $cuenta ?: '—' }}</strong>.
</p>

<p class="cuerpo">
    Para cualquier validación o consulta sobre la autenticidad del presente documento, agradeceremos comunicarse con el
    área de Recursos Humanos{{ $empresa->email ? ' al correo: ' . $empresa->email : '' }}.
</p>

<p>Sin otro particular, nos reiteramos de ustedes.</p>

<p style="margin-top:20px;">Atentamente,</p>

<div class="firma">
    <div class="linea"></div>
    {{ $firma['nombre'] }}<br>
    {{ $firma['cargo'] }}<br>
    {{ $empresa->razon_social }}
</div>
</body></html>
