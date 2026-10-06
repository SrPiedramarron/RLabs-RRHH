<?php

namespace App\Services;

use App\Mail\SolicitudJefeMail;
use App\Models\Employee;
use App\Models\ParametroLegal;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Mail;

/**
 * Flujo de aprobación por jefe directo: las vacaciones y permisos pasan
 * primero por el jefe del trabajador (pendiente_jefe); cuando él aprueba, o
 * pasado el plazo sin respuesta, la solicitud llega a RRHH (pendiente).
 * La corrección de horas y los trabajadores sin jefe van directo a RRHH.
 */
class SolicitudJefeService
{
    const TIPOS_CON_JEFE = ['vacaciones', 'permiso'];

    public function __construct(private PushNotificationService $push) {}

    /** Datos de estado/jefe con los que debe nacer una solicitud nueva. */
    public function estadoInicial(Employee $empleado, string $tipo): array
    {
        if (in_array($tipo, self::TIPOS_CON_JEFE, true) && $empleado->jefe_directo_id) {
            return ['estado' => 'pendiente_jefe', 'jefe_id' => $empleado->jefe_directo_id];
        }

        return ['estado' => 'pendiente', 'jefe_id' => null];
    }

    public function horasEspera(): float
    {
        try {
            return max(0.0, ParametroLegal::valor('horas_espera_jefe'));
        } catch (\Throwable) {
            return 6.0;
        }
    }

    /** Avisa al jefe de que tiene una solicitud por revisar (push y correo). */
    public function notificarJefe(Solicitud $solicitud): void
    {
        $jefe = $solicitud->jefe;
        if (! $jefe) {
            return;
        }

        $this->push->enviarATrabajador(
            $jefe,
            'Solicitud de tu equipo',
            $solicitud->employee->nombre_completo . ' — ' . $solicitud->tipo_label,
            '/checkin/equipo'
        );

        if ($jefe->email) {
            Mail::to($jefe->email)->queue(new SolicitudJefeMail($solicitud));
        }
    }

    public function aprobar(Solicitud $solicitud, ?string $comentario = null): void
    {
        $solicitud->update([
            'estado'           => 'pendiente',
            'jefe_resuelto_at' => now(),
            'comentario_jefe'  => $comentario,
        ]);

        $solicitud->notificarRrhh();
    }

    public function rechazar(Solicitud $solicitud, string $comentario): void
    {
        $solicitud->update([
            'estado'              => 'rechazada',
            'jefe_resuelto_at'    => now(),
            'comentario_jefe'     => $comentario,
            'comentario_revision' => 'Rechazada por tu jefe: ' . $comentario,
            'revisado_at'         => now(),
        ]);

        app(NotificacionSolicitudService::class)->notificarResultado($solicitud->fresh());
    }

    /** Pasa a RRHH las solicitudes cuyo jefe no respondió en el plazo. */
    public function escalarVencidas(): int
    {
        $limite = now()->subMinutes((int) round($this->horasEspera() * 60));

        $vencidas = Solicitud::where('estado', 'pendiente_jefe')
            ->where('created_at', '<=', $limite)
            ->get();

        foreach ($vencidas as $s) {
            $s->update(['estado' => 'pendiente', 'escalada_at' => now()]);
            $s->notificarRrhh();
        }

        return $vencidas->count();
    }
}
