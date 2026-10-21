<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use App\Models\PlanillaQuincena;
use App\Services\Renta5taCalculator;
use Illuminate\Console\Command;

/**
 * Solo lectura. Por trabajador de planilla muestra si la ficha aplica 5ta, la retención del
 * último mes del histórico (PLAME), lo que calcula hoy el método de la quincena y lo guardado
 * en la quincena, para ver por qué falta o no cuadra la 5ta.
 */
class DiagnosticoQuintaQuincena extends Command
{
    protected $signature = 'rrhh:diagnostico-quinta-quincena {periodo=2026-10} {--company=2}';
    protected $description = 'Diagnóstico (solo lectura) de la 5ta de la quincena frente al histórico PLAME';

    public function handle(Renta5taCalculator $calc): int
    {
        [$y, $m] = array_map('intval', explode('-', $this->argument('periodo')));
        $anterior = sprintf('%04d-%02d', $m === 1 ? $y - 1 : $y, $m === 1 ? 12 : $m - 1);

        $filas = [];
        $empleados = Employee::planilla()->where('company_id', (int) $this->option('company'))->where('active', true)
            ->whereNotNull('sueldo_base')->orderBy('apellidos')->get();

        foreach ($empleados as $e) {
            $sueldo = floatval($e->sueldo_base) + ($e->aplica_asignacion_familiar ? round(\App\Models\ParametroLegal::valor('rmv', "$y-$m-01") * 0.10, 2) : 0);
            $histRet = (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', $anterior)->where('concepto', Renta5taCalculator::CONCEPTO_RETENCION)->sum('monto');
            $histCom = (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', 'like', $y . '-%')->where('concepto', Renta5taCalculator::CONCEPTO_COMISIONES)->sum('monto');
            $cuota   = $calc->calcularNoComisionado($e, $sueldo, $y, $m)['cuota_mensual'];
            $q       = PlanillaQuincena::where('employee_id', $e->id)->where('periodo', $this->argument('periodo'))->first();

            $filas[] = [
                mb_substr("{$e->apellidos} {$e->nombres}", 0, 32),
                $e->aplica_5ta_categoria ? 'SÍ' : 'no',
                number_format($histRet, 2, '.', ''),
                number_format($histCom, 2, '.', ''),
                number_format($cuota, 2, '.', ''),
                $q ? number_format(((float) $q->descuento_5ta_categoria) * 2, 2, '.', '') : '—',
            ];
        }

        $this->table(['Trabajador', 'Ficha 5ta', "Ret. $anterior (hist.)", 'Comisiones año', 'Calc. método quincena (mes)', 'Quincena guardada ×2'], $filas);

        return self::SUCCESS;
    }
}
