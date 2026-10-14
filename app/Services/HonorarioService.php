<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\HonorarioPago;
use App\Models\ParametroLegal;
use Illuminate\Support\Collection;

/**
 * Locadores de servicios (recibo por honorarios): reciben un monto fijo por
 * quincena (mitad del honorario mensual), sin horas ni asistencia. La
 * retención de 4.ª categoría se aplica a cada recibo que supere el umbral,
 * salvo que el locador tenga suspensión de retenciones vigente.
 */
class HonorarioService
{
    public function tasaRetencion(): float
    {
        try {
            return ParametroLegal::valor('tasa_retencion_4ta');
        } catch (\Throwable) {
            return 0.08;
        }
    }

    public function umbralRetencion(): float
    {
        try {
            return ParametroLegal::valor('umbral_retencion_4ta');
        } catch (\Throwable) {
            return 1500.0;
        }
    }

    public function calcularQuincena(int $companyId, string $periodo, int $quincena): Collection
    {
        $locadores = Employee::where('company_id', $companyId)
            ->where('tipo_vinculo', 'locador')
            ->where('active', true)
            ->where('honorario_mensual', '>', 0)
            ->get();

        $inicio = $periodo . '-01';
        $fin    = \Carbon\Carbon::parse($inicio)->endOfMonth()->toDateString();
        $tasa   = $this->tasaRetencion();
        $umbral = $this->umbralRetencion();

        $pagos = collect();
        foreach ($locadores as $e) {
            // Solo quienes estuvieron vigentes en el mes.
            if (($e->fecha_ingreso && $e->fecha_ingreso->toDateString() > $fin)
                || ($e->fecha_cese && $e->fecha_cese->toDateString() < $inicio)) {
                continue;
            }

            $mensual = (float) $e->honorario_mensual;
            $primera = round($mensual / 2, 2);
            $bruto   = $quincena === 1 ? $primera : round($mensual - $primera, 2);

            $retencion = (! $e->suspension_4ta && $bruto > $umbral) ? round($bruto * $tasa, 2) : 0.0;

            $existente = HonorarioPago::where('employee_id', $e->id)->where('periodo', $periodo)->where('quincena', $quincena)->first();

            $pagos->push(HonorarioPago::updateOrCreate(
                ['employee_id' => $e->id, 'periodo' => $periodo, 'quincena' => $quincena],
                [
                    'company_id'      => $companyId,
                    'honorario_bruto' => $bruto,
                    'retencion_4ta'   => $retencion,
                    'neto_pagar'      => round($bruto - $retencion, 2),
                    'suspension_4ta'  => (bool) $e->suspension_4ta,
                ] + ($existente ? [] : ['pagado' => false])
            ));
        }

        return $pagos;
    }
}
