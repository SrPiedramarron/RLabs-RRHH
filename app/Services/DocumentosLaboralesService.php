<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Gratificacion;
use App\Models\IngresoHistorico5ta;
use App\Models\LiquidacionCese;
use App\Models\ParametroLegal;
use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;

/**
 * Datos y textos comunes de los documentos laborales (certificado de
 * trabajo, carta de retiro de CTS, certificado de quinta categoría y
 * documento de liquidación).
 */
class DocumentosLaboralesService
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'setiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /** "7 de octubre de 2026" */
    public function fechaLarga(Carbon|string $fecha): string
    {
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return $f->day . ' de ' . self::MESES[$f->month] . ' de ' . $f->year;
    }

    public function ciudad(?Company $empresa): string
    {
        return $empresa?->ciudad ?: 'Lima';
    }

    /** "el Sr." / "la Sra." según el sexo de la ficha. */
    public function tratamiento(Employee $empleado): array
    {
        return match ($empleado->sexo) {
            'F'     => ['articulo' => 'la', 'titulo' => 'Sra.', 'don' => 'doña'],
            'M'     => ['articulo' => 'el', 'titulo' => 'Sr.', 'don' => 'don'],
            default => ['articulo' => 'el/la', 'titulo' => 'Sr(a).', 'don' => 'don/doña'],
        };
    }

    public function nombreCompleto(Employee $empleado): string
    {
        return mb_strtoupper(trim($empleado->apellidos . ' ' . $empleado->nombres));
    }

    /** Tiempo de servicio entre dos fechas (ambas incluidas): [años, meses, días]. */
    public function tiempoServicio(Carbon $ingreso, Carbon $cese): array
    {
        $fin = $cese->copy()->startOfDay()->addDay();
        $ini = $ingreso->copy()->startOfDay();

        $anios = (int) $ini->diffInYears($fin);
        $ini2  = $ini->copy()->addYears($anios);
        $meses = (int) $ini2->diffInMonths($fin);
        $dias  = (int) $ini2->copy()->addMonths($meses)->diffInDays($fin);

        return [$anios, $meses, $dias];
    }

    public function etiquetaPensiones(?string $sistema): string
    {
        return match (true) {
            $sistema === 'onp'                    => 'ONP',
            str_starts_with((string) $sistema, 'afp_') => mb_strtoupper(substr($sistema, 4)),
            default                               => '—',
        };
    }

    /** 11,039.56 → "ONCE MIL TREINTA Y NUEVE CON 56/100 SOLES" */
    public function montoEnLetras(float $monto): string
    {
        $monto    = round($monto, 2);
        $enteros  = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);

        return mb_strtoupper($this->numeroEnLetras($enteros)) . sprintf(' CON %02d/100 SOLES', $centavos);
    }

    private function numeroEnLetras(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
            'veintisiete', 'veintiocho', 'veintinueve'];
        $decenas  = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
        $centenas = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
            6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

        $menorMil = function (int $x) use ($unidades, $decenas, $centenas): string {
            if ($x === 100) {
                return 'cien';
            }
            $texto = '';
            if ($x >= 100) {
                $texto = $centenas[intdiv($x, 100)] . ' ';
                $x %= 100;
            }
            if ($x < 30) {
                $texto .= $unidades[$x];
            } else {
                $texto .= $decenas[intdiv($x, 10)] . ($x % 10 ? ' y ' . $unidades[$x % 10] : '');
            }

            return trim($texto);
        };

        $partes = [];
        $millones = intdiv($n, 1000000);
        $resto    = $n % 1000000;
        if ($millones > 0) {
            $partes[] = $millones === 1 ? 'un millón' : $menorMil($millones) . ' millones';
        }
        $miles = intdiv($resto, 1000);
        $resto = $resto % 1000;
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'mil' : preg_replace('/uno$/', 'un', $menorMil($miles)) . ' mil';
        }
        if ($resto > 0) {
            $partes[] = $menorMil($resto);
        }

        return trim(implode(' ', $partes));
    }

    // ── Certificado de quinta categoría ──────────────────────────────────────

    /**
     * Montos del ejercicio tomados de lo que el sistema tiene (planillas
     * mensuales, histórico importado, gratificaciones y liquidación por cese).
     * Son un punto de partida: RRHH los revisa y ajusta antes de emitir.
     */
    public function quintaPrefill(Employee $empleado, int $anio): array
    {
        $liquidaciones = PlanillaLiquidacion::where('employee_id', $empleado->id)
            ->where('periodo', 'like', $anio . '-%')
            ->get()
            ->keyBy('periodo');

        $historico = IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', 'like', $anio . '-%')
            ->get();

        $sueldos = $comisiones = $utilidades = $remuVacacional = 0.0;
        $gratificacion = $bonificaciones = $retenciones = 0.0;

        foreach ($liquidaciones as $l) {
            $sueldos += (float) $l->sueldo_proporcional + (float) $l->asignacion_familiar
                + (float) $l->importe_horas_extra_diurnas + (float) $l->importe_horas_extra_nocturnas
                + (float) $l->bono_encargatura + (float) $l->bonos_especiales;
            $comisiones    += (float) $l->comisiones;
            $remuVacacional += (float) $l->vacaciones;
            $retenciones   += (float) ($l->retencion_5ta_manual ?: $l->descuento_5ta_categoria);
        }

        // Meses sin planilla en el sistema: se usa el histórico importado.
        foreach ($historico->groupBy('periodo') as $periodo => $filas) {
            $tienePlanilla = $liquidaciones->has($periodo);
            foreach ($filas as $h) {
                $c = mb_strtoupper($h->concepto);
                $m = (float) $h->monto;

                if ($c === 'RETENCION_5TA_APLICADA') {
                    $retenciones += $tienePlanilla ? 0 : $m;
                } elseif ($c === 'UTILIDADES') {
                    $utilidades += $m;
                } elseif (str_starts_with($c, 'GRATIFICACION')) {
                    $gratificacion += $m;
                } elseif ($tienePlanilla) {
                    continue;
                } elseif ($c === 'COMISIONES') {
                    $comisiones += $m;
                } elseif ($c === 'SUELDO + ASIG FAM') {
                    $sueldos += $m;
                }
            }
        }

        foreach (Gratificacion::where('employee_id', $empleado->id)->where('anio', $anio)->get() as $g) {
            $gratificacion  += (float) $g->monto_gratificacion;
            $bonificaciones += (float) $g->bonificacion_extraordinaria;
        }

        $vacTruncas = $gratTrunca = 0.0;
        $cese = LiquidacionCese::where('employee_id', $empleado->id)->whereYear('fecha_cese', $anio)->latest('fecha_cese')->first();
        if ($cese) {
            $remuVacacional += (float) $cese->remuneracion_vacacional_pendiente;
            $vacTruncas      = (float) $cese->monto_vacaciones_truncas;
            $gratTrunca      = (float) $cese->monto_gratificacion_trunca;
            $bonificaciones += (float) $cese->bonificacion_extraordinaria_trunca;
        }

        $uit = $this->uit($anio);

        return [
            'sueldos'         => round($sueldos, 2),
            'comisiones'      => round($comisiones, 2),
            'utilidades'      => round($utilidades, 2),
            'gratificacion'   => round($gratificacion, 2),
            'bonificaciones'  => round($bonificaciones, 2),
            'remu_vacacional' => round($remuVacacional, 2),
            'vac_truncas'     => round($vacTruncas, 2),
            'grat_trunca'     => round($gratTrunca, 2),
            'deduccion'       => round(7 * $uit, 2),
            'retenciones'     => round($retenciones, 2),
            'devolucion'      => 0.0,
            'uit'             => $uit,
        ];
    }

    public function uit(int $anio): float
    {
        try {
            return ParametroLegal::valor(ParametroLegal::UIT, $anio . '-12-31');
        } catch (\Throwable) {
            return 5500.0;
        }
    }

    /** Impuesto anual por tramos (5/20/35 UIT al 8/14/17/20%). */
    public function impuestoAnual(float $rentaNeta, float $uit): float
    {
        $impuesto = 0.0;
        foreach (\App\Services\Renta5taCalculator::TRAMOS_UIT as $t) {
            $desde = $t['desde'] * $uit;
            $hasta = $t['hasta'] === null ? INF : $t['hasta'] * $uit;
            if ($rentaNeta > $desde) {
                $impuesto += (min($rentaNeta, $hasta) - $desde) * $t['tasa'];
            }
        }

        return round($impuesto, 2);
    }
}
