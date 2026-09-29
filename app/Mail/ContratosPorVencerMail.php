<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ContratosPorVencerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Collection $trabajadores,
        public int $dias,
    ) {}

    public function build()
    {
        return $this
            ->subject("⏰ {$this->trabajadores->count()} contrato(s) vencen en {$this->dias} días — {$this->company->razon_social}")
            ->view('emails.contratos-por-vencer');
    }
}
