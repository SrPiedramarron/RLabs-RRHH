<?php

namespace App\Console\Commands;

use App\Services\SolicitudJefeService;
use Illuminate\Console\Command;

class EscalarSolicitudesJefe extends Command
{
    protected $signature = 'solicitudes:escalar-jefe';
    protected $description = 'Pasa a RRHH las solicitudes cuyo jefe directo no respondió en el plazo configurado';

    public function handle(SolicitudJefeService $servicio): int
    {
        $n = $servicio->escalarVencidas();
        $this->info("$n solicitud(es) pasadas a RRHH.");

        return self::SUCCESS;
    }
}
