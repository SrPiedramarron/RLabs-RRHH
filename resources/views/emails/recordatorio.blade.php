<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0; padding:0; background:#F0F4FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FF; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="background:#1E3A5F; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:700;">RLabs RRHH</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">{{ $titulo }}</h1>
                            <p style="font-size:14px; color:#6B7280; margin:0 0 20px;">{{ $empresa }} — {{ $intro }}</p>

                            @if(count($filas))
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <tr style="background:#F9FAFB;">
                                    @foreach($columnas as $c)
                                        <td style="padding:8px 12px; font-weight:700; color:#374151;">{{ $c }}</td>
                                    @endforeach
                                </tr>
                                @foreach($filas as $fila)
                                <tr style="border-top:1px solid #E5E7EB;">
                                    @foreach($fila as $celda)
                                        <td style="padding:8px 12px;">{{ $celda }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </table>
                            @endif

                            <p style="font-size:12px; color:#9CA3AF; margin:20px 0 0;">
                                Recordatorio automático: {{ $cuando }}. Se configura en Administración → Recordatorios.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
