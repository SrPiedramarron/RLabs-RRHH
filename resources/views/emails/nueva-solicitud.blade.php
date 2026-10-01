<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva solicitud</title>
</head>
<body style="margin:0; padding:0; background:#F0F4FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FF; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(37,99,235,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg, #1E3A5F 0%, #2563EB 100%); padding:20px 24px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:700;">RLabs RRHH</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <div style="font-size:38px; margin-bottom:12px;">📩</div>

                            <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">
                                Nueva solicitud: {{ $solicitud->tipo_label }}
                            </h1>

                            <p style="font-size:14px; color:#6B7280; margin:0 0 20px;">
                                {{ $solicitud->company->razon_social }} — un trabajador envió una solicitud desde la app.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <tr style="border-top:1px solid #E5E7EB;">
                                    <td style="padding:8px 12px; font-weight:700; color:#374151; width:140px;">Trabajador</td>
                                    <td style="padding:8px 12px;">{{ $solicitud->employee->nombre_completo }}</td>
                                </tr>
                                <tr style="border-top:1px solid #E5E7EB; background:#F9FAFB;">
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Tipo</td>
                                    <td style="padding:8px 12px;">{{ $solicitud->tipo_label }}</td>
                                </tr>
                                @if($solicitud->fecha_inicio)
                                <tr style="border-top:1px solid #E5E7EB;">
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Fechas</td>
                                    <td style="padding:8px 12px;">
                                        {{ $solicitud->fecha_inicio->format('d/m/Y') }}
                                        @if($solicitud->fecha_fin && !$solicitud->fecha_fin->equalTo($solicitud->fecha_inicio))
                                            al {{ $solicitud->fecha_fin->format('d/m/Y') }}
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if($solicitud->motivo)
                                <tr style="border-top:1px solid #E5E7EB; background:#F9FAFB;">
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Motivo</td>
                                    <td style="padding:8px 12px;">{{ $solicitud->motivo }}</td>
                                </tr>
                                @endif
                            </table>

                            <p style="font-size:12px; color:#9CA3AF; margin:20px 0 0;">
                                Ingresa al sistema, sección "Solicitudes", para aprobarla o rechazarla.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
