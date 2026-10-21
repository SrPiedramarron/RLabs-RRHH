<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Código de recuperación</title></head>
<body style="margin:0; padding:0; background:#F0F4FF; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FF; padding:24px 0;">
        <tr><td align="center">
            <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden;">
                <tr><td style="background:#1E3A5F; padding:20px 24px;"><span style="color:#ffffff; font-size:16px; font-weight:700;">RLabs RRHH</span></td></tr>
                <tr><td style="padding:28px 24px;">
                    <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">Hola, {{ $nombre }}</h1>
                    <p style="font-size:14px; color:#374151; margin:0 0 16px;">Usa este código para crear una contraseña nueva en la app de asistencia:</p>
                    <p style="font-size:34px; font-weight:800; letter-spacing:8px; color:#1E3A5F; margin:0 0 16px; text-align:center;">{{ $codigo }}</p>
                    <p style="font-size:12px; color:#6B7280; margin:0;">Vence en {{ $minutos }} minutos. Si no lo pediste tú, ignora este mensaje: tu contraseña no cambia.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
