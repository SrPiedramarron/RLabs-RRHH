<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use App\Models\ParametroLegal;
use App\Services\Renta5taCalculator;
use Illuminate\Console\Command;

/**
 * Solo lectura. Réplica del cálculo de 5ta de vendedores de la hoja "RTA 5TA VTAS" de RRHH, hecha
 * con el histórico del sistema, para el mes M (por defecto 9 = como está su hoja hoy) y para el
 * siguiente. Muestra cada pieza para compararla con la hoja.
 *
 *   proyección = (último sueldo + promedio de las 3 últimas comisiones) × meses restantes
 *              + (ídem × 1,09 × gratificaciones pendientes) + total percibido (sin utilidades)
 *   cuota = (impuesto de (proyección − 7 UIT) − retenciones previas) / meses restantes
 */
class DiagnosticoQuintaVendedores extends Command
{
    protected $signature = 'rrhh:diagnostico-quinta-vendedores {--company=2} {--esperado= : dni:cuota,dni:cuota (la de la hoja de RRHH)}';
    protected $description = 'Diagnóstico (solo lectura) de la 5ta de vendedores frente a la hoja RTA 5TA VTAS';

    public function handle(): int
    {
        $limpiar = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');
        $esperado = [];
        foreach (array_filter(explode(',', (string) $this->option('esperado'))) as $par) {
            [$d, $v] = array_pad(explode(':', $par), 2, null);
            $esperado[$limpiar($d)] = (float) $v;
        }

        $uit = ParametroLegal::valor(ParametroLegal::UIT, '2026-10-01');
        $filas = [];

        $ids = IngresoHistorico5ta::where('company_id', (int) $this->option('company'))->where('periodo', 'like', '2026-%')
            ->where('concepto', Renta5taCalculator::CONCEPTO_COMISIONES)->distinct()->pluck('employee_id');

        foreach (Employee::withoutGlobalScopes()->whereIn('id', $ids)->where('active', true)->orderBy('apellidos')->get() as $e) {
            foreach ([9, 10] as $M) {
                $r = $this->replica($e, $M, $uit);
                $filas[] = [
                    mb_substr("{$e->apellidos} {$e->nombres}", 0, 24), $M,
                    $this->n($r['ss']), $this->n($r['prom']), $this->n($r['percibido']), $this->n($r['proy']),
                    $this->n($r['impuesto']), $this->n($r['ret']), $this->n($r['cuota']),
                    $M === 9 && isset($esperado[$limpiar($e->dni)]) ? $this->n($esperado[$limpiar($e->dni)]) : '',
                ];
            }
        }

        $this->table(['Vendedor', 'Mes', 'Último sueldo', 'Prom. 3 comis.', 'Percibido', 'Proyección', 'Impuesto', 'Ret. previas', 'Cuota', 'Cuota hoja RRHH'], $filas);

        return self::SUCCESS;
    }

    private function n(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    private function replica(Employee $e, int $M, float $uit): array
    {
        $h = fn (string $periodo, string $concepto) => (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', $periodo)->where('concepto', $concepto)->sum('monto');
        $p = fn (int $mes) => sprintf('2026-%02d', $mes);

        $ss   = $h($p($M - 1), Renta5taCalculator::CONCEPTO_SUELDO);
        $prom = ($h($p($M - 1), 'COMISIONES') + $h($p($M - 2), 'COMISIONES') + $h($p($M - 3), 'COMISIONES')) / 3;

        $percibido = (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', '<', $p($M))->where('periodo', 'like', '2026-%')
            ->whereNotIn('concepto', [Renta5taCalculator::CONCEPTO_RETENCION, Renta5taCalculator::CONCEPTO_UTILIDADES, 'RENTA UTILIDAD', 'BASE_PLAME_5TA'])->sum('monto');
        $ret = (float) IngresoHistorico5ta::where('employee_id', $e->id)->where('periodo', '<', $p($M))->where('periodo', 'like', '2026-%')
            ->where('concepto', Renta5taCalculator::CONCEPTO_RETENCION)->sum('monto');

        $restantes = 13 - $M;
        $pendientes = $M <= 7 ? 2 : 1; // pasada la gratificación de julio solo falta la de diciembre
        $proy = ($ss + $prom) * $restantes + ($ss + $prom) * 1.09 * $pendientes + $percibido;

        $base = max(0, $proy - 7 * $uit);
        $imp  = min($base, 5 * $uit) * 0.08 + min(max(0, $base - 5 * $uit), 15 * $uit) * 0.14
              + min(max(0, $base - 20 * $uit), 15 * $uit) * 0.17 + max(0, $base - 35 * $uit) * 0.20;

        return [
            'ss' => $ss, 'prom' => $prom, 'percibido' => $percibido, 'proy' => $proy, 'impuesto' => $imp, 'ret' => $ret,
            'cuota' => max(0, $imp - $ret) / $restantes,
        ];
    }
}
