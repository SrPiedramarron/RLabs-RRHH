<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use Illuminate\Console\Command;

/**
 * Carga única (oct. 2026): retención y base de renta de 5.ª categoría por
 * trabajador y mes (ene-sep 2026), tomadas del PLAME R03 que entregó RRHH.
 *
 *  - Retención: solo se agrega donde el histórico NO tiene retención de ese
 *    mes (p. ej. agosto y septiembre). Si ya hay un valor distinto, solo se
 *    informa la diferencia y NO se pisa.
 *  - Base declarada: se guarda como concepto aparte (BASE_PLAME_5TA), que el
 *    cálculo de la retención no usa.
 *
 * Lee un JSON local no versionado. Sin --aplicar solo simula.
 */
class CargarQuintaPlame extends Command
{
    protected $signature = 'rrhh:cargar-quinta-plame {--aplicar : Guardar (sin esto solo simula)} {--company=2} {--archivo=storage/app/import/quinta_plame_2026.json}';
    protected $description = 'Carga retención y base de 5ta categoría (PLAME R03) al histórico (carga única)';

    public const CONCEPTO_BASE = 'BASE_PLAME_5TA';
    private const FUENTE = 'plame_r03';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $company = (int) $this->option('company');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $limpiar   = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');
        $empleados = Employee::withoutGlobalScopes()->where('company_id', $company)->get()->keyBy(fn ($e) => $limpiar($e->dni));

        $nuevas = $iguales = $bases = 0;
        $diferencias = [];
        $sinTrabajador = [];

        foreach ($filas as $f) {
            $e = $empleados[$limpiar($f['dni'])] ?? null;
            if (! $e) {
                $sinTrabajador[$f['dni']] = $f['nombre'];
                continue;
            }

            // Retención
            $plame = (float) ($f['retencion'] ?? 0);
            if ($plame > 0) {
                $existente = IngresoHistorico5ta::where('employee_id', $e->id)
                    ->where('periodo', $f['periodo'])
                    ->where('concepto', 'RETENCION_5TA_APLICADA')
                    ->sum('monto');

                if ($existente <= 0) {
                    $nuevas++;
                    if ($aplicar) {
                        IngresoHistorico5ta::create([
                            'employee_id' => $e->id, 'company_id' => $e->company_id,
                            'periodo' => $f['periodo'], 'concepto' => 'RETENCION_5TA_APLICADA',
                            'monto' => $plame, 'fuente' => self::FUENTE,
                        ]);
                    }
                } elseif (abs($existente - $plame) > 0.01) {
                    $diferencias[] = [$e->apellidos, $f['periodo'], round($existente, 2), $plame, round($plame - $existente, 2)];
                } else {
                    $iguales++;
                }
            }

            // Base declarada
            $base = (float) ($f['base'] ?? 0);
            if ($base > 0) {
                $bases++;
                if ($aplicar) {
                    IngresoHistorico5ta::updateOrCreate(
                        ['employee_id' => $e->id, 'periodo' => $f['periodo'], 'concepto' => self::CONCEPTO_BASE],
                        ['company_id' => $e->company_id, 'monto' => $base, 'fuente' => self::FUENTE]
                    );
                }
            }
        }

        $this->line("Retenciones nuevas (meses que faltaban): $nuevas | ya coincidían: $iguales | bases declaradas: $bases");

        if ($diferencias) {
            $this->warn(count($diferencias) . ' mes(es) donde el sistema ya tiene una retención distinta (no se pisa):');
            $this->table(['Trabajador', 'Mes', 'Sistema', 'PLAME', 'Dif.'], $diferencias);
        }
        if ($sinTrabajador) {
            $this->warn('Sin trabajador en la empresa: ' . implode(', ', array_map(fn ($d, $n) => "$d $n", array_keys($sinTrabajador), $sinTrabajador)));
        }

        return self::SUCCESS;
    }
}
