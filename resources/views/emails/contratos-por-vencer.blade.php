<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contratos por vencer</title>
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
                            <div style="font-size:38px; margin-bottom:12px;">⏰</div>

                            <h1 style="font-size:18px; color:#111827; margin:0 0 8px;">
                                {{ $trabajadores->count() }} contrato(s) vencen en {{ $dias }} días
                            </h1>

                            <p style="font-size:14px; color:#6B7280; margin:0 0 20px;">
                                {{ $company->razon_social }} — revisa si corresponde renovar o gestionar el cese a tiempo.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <tr style="background:#F9FAFB;">
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Trabajador</td>
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">DNI</td>
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Cargo</td>
                                    <td style="padding:8px 12px; font-weight:700; color:#374151;">Fin de contrato</td>
                                </tr>
                                @foreach($trabajadores as $t)
                                <tr style="border-top:1px solid #E5E7EB;">
                                    <td style="padding:8px 12px;">{{ $t->apellidos }}, {{ $t->nombres }}</td>
                                    <td style="padding:8px 12px;">{{ $t->dni }}</td>
                                    <td style="padding:8px 12px;">{{ $t->cargo ?? '—' }}</td>
                                    <td style="padding:8px 12px; font-weight:600;">{{ $t->fecha_fin_contrato?->format('d/m/Y') }}</td>
                                </tr>
                                @endforeach
                            </table>

                            <p style="font-size:12px; color:#9CA3AF; margin:20px 0 0;">
                                Si se renueva el contrato, recuerda usar el botón "Renovar contrato" en la ficha del trabajador para que la fecha de cese se actualice automáticamente.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
