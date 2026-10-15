<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

/**
 * Carga única (oct. 2026): alinea sueldo base, asignación familiar, sistema de
 * pensiones y comisión de flujo AFP de cada ficha con la planilla de RRHH
 * (Excel de la 1.ª quincena de octubre). Lee un JSON local no versionado.
 * Sin --aplicar solo muestra las diferencias.
 */
class AlinearFichasPlanilla extends Command
{
    protected $signature = 'rrhh:alinear-fichas-planilla {--aplicar : Guardar (sin esto solo simula)} {--company=2} {--archivo=storage/app/import/fichas_planilla_excel.json}';
    protected $description = 'Alinea sueldo, asignación familiar, AFP/ONP y comisión de flujo de las fichas con el Excel de planilla (carga única)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $company = (int) $this->option('company');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SOLO COMPARACIÓN (agrega --aplicar para corregir) ==');

        $limpiar   = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');
        $empleados = Employee::withoutGlobalScopes()->where('company_id', $company)->get()->keyBy(fn ($e) => $limpiar($e->dni));

        $tabla = [];
        $sinFicha = [];

        foreach ($filas as $f) {
            $e = $empleados[$limpiar($f['dni'])] ?? null;
            if (! $e) {
                $sinFicha[] = $f['dni'] . ' ' . $f['nombre'];
                continue;
            }

            $cambios = [];

            if (abs((float) $e->sueldo_base - (float) $f['sueldo']) > 0.009) {
                $cambios['sueldo_base'] = [(float) $e->sueldo_base, (float) $f['sueldo']];
            }
            if ((bool) $e->aplica_asignacion_familiar !== (bool) $f['asig']) {
                $cambios['aplica_asignacion_familiar'] = [(bool) $e->aplica_asignacion_familiar ? 'sí' : 'no', $f['asig'] ? 'sí' : 'no'];
            }
            if ($f['sistema_pensiones'] && $e->sistema_pensiones !== $f['sistema_pensiones']) {
                $cambios['sistema_pensiones'] = [$e->sistema_pensiones, $f['sistema_pensiones']];
            }
            // La comisión de flujo solo tiene sentido para AFP.
            $flujoExcel = $f['flujo'] && str_starts_with((string) $f['sistema_pensiones'], 'afp_');
            if ((bool) $e->aplica_comision_flujo_afp !== $flujoExcel) {
                $cambios['aplica_comision_flujo_afp'] = [$e->aplica_comision_flujo_afp ? 'sí' : 'no', $flujoExcel ? 'sí' : 'no'];
            }

            if (! $cambios) {
                continue;
            }

            foreach ($cambios as $campo => [$antes, $despues]) {
                $tabla[] = [$e->id, $e->apellidos, $campo, $antes, $despues];
            }

            if ($aplicar) {
                $nuevo = [];
                foreach ($cambios as $campo => [, $despues]) {
                    $nuevo[$campo] = match ($campo) {
                        'aplica_asignacion_familiar' => $f['asig'],
                        'aplica_comision_flujo_afp'  => $flujoExcel,
                        'sueldo_base'                => (float) $f['sueldo'],
                        default                      => $despues,
                    };
                }
                $e->forceFill($nuevo)->save();
            }
        }

        $this->table(['id', 'Trabajador', 'Campo', 'Ficha', 'Excel'], $tabla);

        if ($sinFicha) {
            $this->warn('Del Excel sin ficha en la empresa: ' . implode(', ', $sinFicha));
        }

        return self::SUCCESS;
    }
}
