<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de tu solicitud</title>
</head>
<body style="margin:0; padding:0; background:#F0F4FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FF; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(37,99,235,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg, #1E3A5F 0%, #2563EB 100%); padding:20px 24px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:700;">RLabs RRHH</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <div style="font-size:38px; margin-bottom:12px;">{{ $aprobada ? '✅' : '❌' }}</div>

                            <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">
                                Tu solicitud de {{ strtolower($solicitud->tipo_label) }} fue {{ $aprobada ? 'aprobada' : 'rechazada' }}
                            </h1>

                            <p style="font-size:14px; color:#6B7280; margin:0 0 20px;">
                                Hola {{ $solicitud->employee->nombres }}, este es el resultado de tu solicitud enviada el {{ $solicitud->created_at->format('d/m/Y') }}.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#F9FAFB; border-radius:10px; padding:16px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:4px 16px; font-size:13px; color:#374151;">
                                        <strong>Tipo:</strong> {{ $solicitud->tipo_label }}<br>
                                        @if($solicitud->tipo === 'correccion_horas')
                                            <strong>Fecha:</strong> {{ $solicitud->fecha_registro?->format('d/m/Y') }}<br>
                                        @else
                                            <strong>Desde:</strong> {{ $solicitud->fecha_inicio?->format('d/m/Y') }}<br>
                                            <strong>Hasta:</strong> {{ $solicitud->fecha_fin?->format('d/m/Y') }}<br>
                                        @endif
                                        @if($solicitud->motivo)
                                            <strong>Motivo:</strong> {{ $solicitud->motivo }}<br>
                                        @endif
                                        @if(!$aprobada && $solicitud->comentario_revision)
                                            <strong style="color:#DC2626;">Motivo del rechazo:</strong> {{ $solicitud->comentario_revision }}
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:12px; color:#9CA3AF; margin:0;">
                                Puedes ver el detalle completo desde la app, en la sección "Solicitudes".
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
