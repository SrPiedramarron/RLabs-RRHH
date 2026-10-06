<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de tu equipo</title>
</head>
<body style="margin:0; padding:0; background:#F0F4FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FF; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="background:#1E3A5F; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:700;">RLabs RRHH</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">
                                {{ $solicitud->employee->nombre_completo }} pidió: {{ $solicitud->tipo_label }}
                            </h1>
                            <p style="font-size:14px; color:#6B7280; margin:0 0 16px;">
                                Como su jefe directo, tu aprobación es el primer paso antes de pasar a RRHH.
                            </p>
                            @if($solicitud->fecha_inicio)
                            <p style="font-size:14px; margin:0 0 8px;">
                                <strong>Fechas:</strong> {{ $solicitud->fecha_inicio->format('d/m/Y') }}
                                @if($solicitud->fecha_fin && !$solicitud->fecha_fin->equalTo($solicitud->fecha_inicio))
                                    al {{ $solicitud->fecha_fin->format('d/m/Y') }}
                                @endif
                            </p>
                            @endif
                            @if($solicitud->motivo)
                            <p style="font-size:14px; margin:0 0 16px;"><strong>Motivo:</strong> {{ $solicitud->motivo }}</p>
                            @endif
                            <p style="margin:20px 0;">
                                <a href="{{ url('/checkin/equipo') }}" style="background:#2563EB; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">Revisar solicitud</a>
                            </p>
                            <p style="font-size:12px; color:#9CA3AF; margin:0;">
                                Si no respondes a tiempo, la solicitud pasa automáticamente a RRHH.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
