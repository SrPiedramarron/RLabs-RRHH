<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use App\Models\ParametroLegal;
use App\Services\Renta5taCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga al histórico de 5ta los ingresos mensuales (sueldo, horas extras, movilidad, bono y
 * utilidades sin retención aparte) tal como están en la hoja "RTA 5TA" de RRHH, y compara el
 * total anual y la cuota del sistema con los de esa hoja. Lee un JSON local no versionado.
 */
class CargarIngresos5ta extends Command
{
    protected $signature = 'rrhh:cargar-ingresos-5ta {--aplicar : Guardar (sin esto solo simula)} {--company=2} {--periodo=2026-10} {--vendedores : Compara con el método de vendedores (hoja RTA 5TA VTAS)} {--archivo=storage/app/import/ingresos_5ta_rta_2026.json}';
    protected $description = 'Carga ingresos mensuales de la hoja RTA 5TA al histórico de 5ta y compara con su total (carga única)';

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
            if (empty($f['dni'])) {
                $this->warn("Sin DNI en el archivo: {$f['nombre']}");
                continue;
            }
            $e = Employee::withoutGlobalScopes()->where('company_id', $companyId)->get()
                ->first(fn ($x) => $limpiar($x->dni) === $limpiar($f['dni']));
            if (! $e) {
                $this->warn("Sin trabajador: {$f['dni']} {$f['nombre']}");
                continue;
            }

            $cambios = 0;
            foreach ($f['items'] as $it) {
                // Con retención aparte de utilidades, estas no entran a la base anual.
                if ($it['concepto'] === Renta5taCalculator::CONCEPTO_UTILIDADES && (float) $f['renta_utilidad'] > 0) {
                    continue;
                }
                $reg = IngresoHistorico5ta::firstOrNew(['employee_id' => $e->id, 'periodo' => $it['periodo'], 'concepto' => $it['concepto']]);
                if ($reg->exists && abs((float) $reg->monto - (float) $it['monto']) < 0.005) {
                    continue;
                }
                $reg->fill(['company_id' => $companyId, 'monto' => $it['monto'], 'fuente' => 'import_excel_RTA 5TA'])->save();
                $cambios++;
            }

            // Retenciones ya aplicadas mes a mes, como las lleva la hoja de RRHH (difieren del PLAME en céntimos).
            foreach (($f['retenciones'] ?? []) as $periodo => $monto) {
                $ret = IngresoHistorico5ta::firstOrNew(['employee_id' => $e->id, 'periodo' => $periodo, 'concepto' => Renta5taCalculator::CONCEPTO_RETENCION]);
                if ($ret->exists && abs((float) $ret->monto - (float) $monto) < 0.005) {
                    continue;
                }
                $ret->fill(['company_id' => $companyId, 'monto' => $monto, 'fuente' => 'import_excel_RTA 5TA'])->save();
                $cambios++;
            }

            if ($this->option('vendedores')) {
                $det = $calc->calcular($e, null, $y, $m);
                $tabla[] = [
                    mb_substr("{$e->apellidos} {$e->nombres}", 0, 26), $cambios,
                    number_format($det['total_percibido'], 2, '.', ''), number_format((float) ($f['percibido_cielo'] ?? 0), 2, '.', ''),
                    number_format($det['ss_prom_com'], 2, '.', ''), number_format($det['proyeccion_al_mes'], 2, '.', ''),
                    number_format($det['impuesto_anual'], 2, '.', ''), number_format($det['retenciones_previas'], 2, '.', ''),
                    number_format($det['cuota_mensual'], 2, '.', ''),
                ];
                continue;
            }

            $sueldo = floatval($e->sueldo_base) + ($e->aplica_asignacion_familiar ? round(ParametroLegal::valor(ParametroLegal::RMV, "$y-$m-01") * 0.10, 2) : 0);
            $det    = $calc->calcularNoComisionado($e, $sueldo, $y, $m);

            $tabla[] = [
                mb_substr("{$e->apellidos} {$e->nombres}", 0, 30),
                $cambios,
                number_format($det['total_remuneraciones'], 2, '.', ''),
                number_format((float) $f['total_cielo'], 2, '.', ''),
                number_format($det['total_remuneraciones'] - (float) $f['total_cielo'], 2, '.', ''),
                number_format($det['cuota_mensual'], 2, '.', ''),
                number_format((float) $f['cuota_cielo'], 2, '.', ''),
            ];
        }

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(
            $this->option('vendedores')
                ? ['Vendedor', 'Registros cambiados', 'Percibido sistema', 'Percibido hoja', 'SS + prom. comis.', 'Proyección', 'Impuesto anual', 'Ret. previas', 'Cuota sistema (mes ' . $m . ')']
                : ['Trabajador', 'Registros cambiados', 'Total sistema', 'Total Excel', 'Dif.', 'Cuota sistema', 'Cuota Excel'],
            $tabla
        );

        return self::SUCCESS;
    }
}
