<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso genérico de recordatorio: título, introducción y una tabla. */
class RecordatorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $titulo,
        public string $intro,
        public array $columnas,
        public array $filas,
        public string $empresa,
        public string $cuando,
    ) {
    }

    public function build()
    {
        return $this->subject($this->titulo . ' — ' . $this->empresa)->view('emails.recordatorio');
    }
}
