<?php

namespace App\Services;

use App\Mail\SolicitudEstadoMail;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Mail;

class NotificacionSolicitudService
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * Avisa al trabajador que su solicitud fue aprobada o rechazada, por
     * los canales que tenga disponibles: push (si tiene algún dispositivo
     * suscrito) y correo (si tiene email registrado). Si no tiene ninguno,
     * simplemente lo verá al entrar a "Mis solicitudes" en la PWA.
     */
    public function notificarResultado(Solicitud $solicitud): void
    {
        $empleado = $solicitud->employee;
        $aprobada = $solicitud->estado === 'aprobada';

        $titulo = $aprobada ? '✅ Solicitud aprobada' : '❌ Solicitud rechazada';
        $cuerpo = $solicitud->tipo_label . ($aprobada ? ' fue aprobada.' : ' fue rechazada.');

        $this->push->enviarATrabajador($empleado, $titulo, $cuerpo, '/checkin/solicitudes');

        if ($empleado->email) {
            Mail::to($empleado->email)->queue(new SolicitudEstadoMail($solicitud));
        }
    }
}
