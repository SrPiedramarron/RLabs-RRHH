<?php

namespace App\Exports;

use App\Models\LiquidacionCese;
use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Planilla para AFPnet: sin cabeceras (el sistema de las AFP la recibe así),
 * una fila por trabajador afiliado a AFP. Columnas:
 *  1 N° · 2 CUSPP · 3 Tipo doc (0=DNI) · 4 N° doc · 5 Ap. paterno ·
 *  6 Ap. materno · 7 Nombres · 8 Relación laboral (S/N) ·
 *  9 Inicio de relación laboral (S/N) · 10 Cese de relación laboral (S/N) ·
 *  11 Excepción de aportar (vacío) · 12 Remuneración asegurable ·
 *  13-15 Aportes voluntarios (0) · 16 Trabajo de riesgo (N) · 17 vacío
 * Formato confirmado con RRHH (Cielo, oct. 2026) con su archivo de ejemplo.
 */
class AfpnetExport implements FromArray
{
    private const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA', 'VAN', 'VON', 'DA', 'DI'];

    public function __construct(private Collection $liquidaciones, private string $periodo)
    {
    }

    public function array(): array
    {
        $inicioMes = Carbon::parse($this->periodo . '-01')->startOfMonth();
        $finMes    = $inicioMes->copy()->endOfMonth();

        $filas = [];
        $n = 1;

        foreach ($this->liquidaciones as $l) {
            $emp = $l->employee;
            if (! $emp || ! str_starts_with((string) $l->sistema_pensiones, 'afp_')) {
                continue;
            }

            [$paterno, $materno] = $this->separarApellidos((string) $emp->apellidos);

            $inicio = $emp->fecha_ingreso && $emp->fecha_ingreso->betweenIncluded($inicioMes, $finMes);
            $cese   = $emp->fecha_cese && $emp->fecha_cese->betweenIncluded($inicioMes, $finMes);

            $filas[] = [
                $n++,
                (string) ($emp->cuspp ?? ''),
                0,
                $emp->dni,
                $paterno,
                $materno,
                (string) $emp->nombres,
                'S',
                $inicio ? 'S' : 'N',
                $cese ? 'S' : 'N',
                '',
                $this->remuneracionAsegurable($l),
                0, 0, 0,
                'N',
                '',
            ];
        }

        return $filas;
    }

    /**
     * Ingresos afectos a AFP del mes: la misma base sobre la que se calculó
     * el descuento en planilla (bruto + subsidios) y, si el trabajador cesó
     * ese mes, las vacaciones de la liquidación (truncas + remuneración
     * vacacional pendiente), que también tributan a la AFP.
     */
    public function remuneracionAsegurable(PlanillaLiquidacion $l): float
    {
        $base = (float) $l->remuneracion_bruta
              + (float) $l->subsidio_enfermedad
              + (float) $l->subsidio_maternidad;

        $cese = LiquidacionCese::where('employee_id', $l->employee_id)
            ->whereRaw("DATE_FORMAT(fecha_cese, '%Y-%m') = ?", [$this->periodo])
            ->first();

        if ($cese) {
            $base += (float) $cese->monto_vacaciones_truncas + (float) $cese->remuneracion_vacacional_pendiente;
        }

        return round($base, 2);
    }

    /** "DE LA CRUZ PEREZ" → ["DE LA CRUZ", "PEREZ"]; "LOPEZ YARINGAÑO" → ["LOPEZ", "YARINGAÑO"]. */
    private function separarApellidos(string $apellidos): array
    {
        $tokens = preg_split('/\s+/', trim($apellidos), -1, PREG_SPLIT_NO_EMPTY);
        if (count($tokens) <= 1) {
            return [$tokens[0] ?? '', ''];
        }

        $i = 0;
        while ($i < count($tokens) - 1 && in_array(mb_strtoupper($tokens[$i]), self::PARTICULAS, true)) {
            $i++;
        }

        return [
            implode(' ', array_slice($tokens, 0, $i + 1)),
            implode(' ', array_slice($tokens, $i + 1)),
        ];
    }
}
