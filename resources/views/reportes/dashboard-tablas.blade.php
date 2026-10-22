<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
    @page { margin: 28px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .sub { color: #444; margin: 0 0 2px; font-size: 9px; }
    .nota { color: #666; margin: 0 0 10px; font-size: 8px; }
    h2 { font-size: 11px; margin: 14px 0 4px; border-bottom: 1px solid #1E3A5F; padding-bottom: 2px; color: #1E3A5F; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1E3A5F; color: #fff; text-align: left; padding: 3px 4px; font-size: 8px; }
    td { padding: 2.5px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
    tr:nth-child(even) td { background: #f6f7f9; }
    .num { text-align: right; }
    .kpis td { border: 0; padding: 4px 8px 4px 0; width: 25%; }
    .kpis b { font-size: 13px; display: block; }
    .kpis span { color: #555; font-size: 8px; }
    .salto { page-break-before: always; }
</style></head>
<body>
    <h1>{{ $r['titulo'] }}</h1>
    <p class="sub">{{ $r['subtitulo'] }}</p>
    <p class="nota">{{ $r['nota'] ?? '' }} Generado el {{ now()->format('d/m/Y H:i') }}.</p>

    <table class="kpis">
        @foreach(array_chunk($r['kpis'], 4) as $fila)
        <tr>
            @foreach($fila as $k)
            <td><b>{{ $k[1] }}</b><span>{{ $k[0] }}</span></td>
            @endforeach
        </tr>
        @endforeach
    </table>

    @foreach(array_filter($r['hojas'], fn ($h) => $h['pdf']) as $h)
        <h2>{{ $h['titulo'] }}</h2>
        @if(count($h['filas']))
        <table>
            <thead><tr>@foreach($h['columnas'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($h['filas'] as $f)
                <tr>@foreach($f as $v)<td class="{{ is_numeric($v) ? 'num' : '' }}">{{ is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.') : $v }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
        @else
            <p class="nota">Sin datos en el periodo.</p>
        @endif
    @endforeach
</body></html>
