<?php

namespace App\Mail;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudEstadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Solicitud $solicitud) {}

    public function build()
    {
        $aprobada = $this->solicitud->estado === 'aprobada';

        return $this
            ->subject(($aprobada ? '✅ Solicitud aprobada' : '❌ Solicitud rechazada') . ' — ' . $this->solicitud->tipo_label)
            ->view('emails.solicitud-estado')
            ->with([
                'solicitud' => $this->solicitud,
                'aprobada'  => $aprobada,
            ]);
    }
}
