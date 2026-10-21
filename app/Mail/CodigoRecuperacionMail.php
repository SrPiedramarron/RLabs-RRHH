<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Código de 6 dígitos para recuperar la contraseña de la app de asistencia. */
class CodigoRecuperacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $nombre, public string $codigo, public int $minutos = 15)
    {
    }

    public function build()
    {
        return $this->subject('Tu código para recuperar la contraseña — RLabs RRHH')->view('emails.codigo-recuperacion');
    }
}
