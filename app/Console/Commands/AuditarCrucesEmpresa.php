<?php

namespace App\Console\Commands;

use App\Services\AuditoriaCrucesService;
use Illuminate\Console\Command;

/**
 * Solo lectura. Busca datos que se cruzan entre empresas: fichas con sede/área/horario de otra empresa, registros
 * (asistencia, planilla, CTS, solicitudes, etc.) con empresa distinta a la del trabajador, y áreas/sedes/horarios
 * repetidos dentro de la misma empresa. El aviso semanal por correo se configura en Administración → Recordatorios.
 */
class AuditarCrucesEmpresa extends Command
{
    protected $signature = 'rrhh:auditar-cruces-empresa';
    protected $description = 'Revisa (solo lectura) que nada se cruce entre empresas';

    public function handle(AuditoriaCrucesService $servicio): int
    {
        [$filas, $total] = $servicio->revisar();

        $this->table(['', 'Revisión', 'Casos', 'Ejemplos'], $filas);
        $this->line($total === 0 ? '<info>Sin cruces de datos entre empresas.</info>' : "<error>{$total} cruce(s) por corregir.</error>");

        return self::SUCCESS;
    }
}
