<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use App\Models\ParametroLegal;
use App\Services\Renta5taCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga al histórico de 5ta las utilidades de marzo de quienes NO tuvieron retención
 * aparte (RENTA UTILIDAD): esas utilidades entran a la renta anual. Compara además la
 * remuneración anual proyectada por el sistema con la de la hoja "RTA 5TA" de RRHH.
 * Lee un JSON local no versionado (datos personales).
 */
class CargarUtilidades5ta extends Command
{
    protected $signature = 'rrhh:cargar-utilidades-5ta {--aplicar : Guardar (sin esto solo simula)} {--company=2} {--periodo=2026-10} {--archivo=storage/app/import/utilidades_5ta_2026.json}';
    protected $description = 'Carga utilidades de marzo al histórico de 5ta y compara el total anual con la hoja RTA 5TA (carga única)';

    public function handle(Renta5taCalculator $calc): int
    {
        $aplicar   = (bool) $this->option('aplicar');
        $companyId = (int) $this->option('company');
        [$y, $m]   = array_map('intval', explode('-', $this->option('periodo')));
        $filas     = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $limpiar   = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');

        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        DB::beginTransaction();
        $tabla = [];

        foreach ($filas as $f) {
            if (empty($f['dni']) || (float) $f['renta_utilidad'] > 0 || (float) $f['utilidades'] <= 0) {
                continue; // con retención aparte no entran a la base; sin DNI o sin utilidades no hay qué cargar
            }

            $e = Employee::withoutGlobalScopes()->where('company_id', $companyId)->get()
                ->first(fn ($x) => $limpiar($x->dni) === $limpiar($f['dni']));
            if (! $e) {
                $this->warn("Sin trabajador: {$f['dni']} {$f['nombre']}");
                continue;
            }

            $previo = (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', sprintf('%04d-03', $y))
                ->where('concepto', Renta5taCalculator::CONCEPTO_UTILIDADES)->sum('monto');

            IngresoHistorico5ta::updateOrCreate(
                ['employee_id' => $e->id, 'periodo' => sprintf('%04d-03', $y), 'concepto' => Renta5taCalculator::CONCEPTO_UTILIDADES],
                ['company_id' => $companyId, 'monto' => (float) $f['utilidades'], 'fuente' => 'import_excel_RTA 5TA'],
            );

            $sueldo = floatval($e->sueldo_base) + ($e->aplica_asignacion_familiar ? round(ParametroLegal::valor(ParametroLegal::RMV, "$y-$m-01") * 0.10, 2) : 0);
            $det    = $calc->calcularNoComisionado($e, $sueldo, $y, $m);

            $tabla[] = [
                mb_substr("{$e->apellidos} {$e->nombres}", 0, 30),
                number_format($previo, 2, '.', ''),
                number_format((float) $f['utilidades'], 2, '.', ''),
                number_format($det['total_remuneraciones'], 2, '.', ''),
                number_format((float) $f['total_cielo'], 2, '.', ''),
                number_format($det['cuota_mensual'], 2, '.', ''),
                number_format((float) $f['cuota_cielo'], 2, '.', ''),
            ];
        }

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(['Trabajador', 'Utilidades antes', 'Utilidades Excel', 'Total sistema', 'Total Excel', 'Cuota sistema', 'Cuota Excel'], $tabla);

        return self::SUCCESS;
    }
}
