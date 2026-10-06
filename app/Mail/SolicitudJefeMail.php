<?php

namespace App\Mail;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudJefeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Solicitud $solicitud)
    {
    }

    public function build()
    {
        return $this->subject('Solicitud de tu equipo: ' . $this->solicitud->tipo_label . ' — ' . $this->solicitud->employee->nombre_completo)
            ->view('emails.solicitud-jefe');
    }
}
