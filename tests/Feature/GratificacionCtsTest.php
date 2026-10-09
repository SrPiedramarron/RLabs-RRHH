<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Gratificacion;
use App\Models\Location;
use App\Models\ParametroLegal;
use App\Models\PlanillaLiquidacion;
use App\Models\Schedule;
use App\Services\PlanillaService;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gratificaciones (julio y diciembre) y CTS (mayo y noviembre). Los montos esperados se derivan a mano:
 *  - Gratificación = remuneración computable ÷ 6 × meses del semestre + 9 % de bonificación extraordinaria.
 *  - CTS = remuneración computable ÷ 12 × meses (+ días ÷ 360), con 1/6 de la gratificación en la remuneración.
 *  - Promedios de comisiones / horas extra solo si hubo en al menos 3 de los 6 meses.
 */
class GratificacionCtsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PlanillaService $servicio;

    private int $dniSeq = 40000000;

    protected function setUp(): void
    {
        parent::setUp();

        ParametroLegal::create(['clave' => ParametroLegal::RMV, 'descripcion' => 'RMV', 'valor' => 1130, 'vigente_desde' => '2025-01-01']);
        ParametroLegal::create(['clave' => ParametroLegal::TASA_ONP, 'descripcion' => 'ONP', 'valor' => 0.13, 'vigente_desde' => '2025-01-01']);

        $this->company = Company::create(['razon_social' => 'EMPRESA TEST SAC', 'ruc' => '20100000001', 'active' => true]);
        $this->servicio = app(PlanillaService::class);
    }

    private function empleado(array $attrs = []): Employee
    {
        $location = Location::firstOrCreate(['company_id' => $this->company->id, 'nombre' => 'Sede'], ['active' => true]);
        $schedule = Schedule::firstOrCreate(['company_id' => $this->company->id, 'nombre' => 'General'], [
            'hora_entrada' => '08:00', 'hora_salida' => '17:00', 'tolerancia_minutos' => 10, 'dias_laborables' => [1, 2, 3, 4, 5],
        ]);

        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'location_id' => $location->id, 'schedule_id' => $schedule->id,
            'nombres' => 'ANA', 'apellidos' => 'TORRES TEST', 'dni' => (string) $this->dniSeq++, 'cargo' => 'Analista',
            'fecha_ingreso' => '2023-01-10', 'sueldo_base' => 3000, 'sistema_pensiones' => 'onp', 'active' => true,
        ], $attrs));
    }

    private function liquidacion(Employee $e, string $periodo, float $comisiones = 0, float $heDiurnas = 0): void
    {
        PlanillaLiquidacion::create([
            'employee_id' => $e->id, 'company_id' => $this->company->id, 'periodo' => $periodo, 'mes_nombre' => $periodo,
            'nombres' => $e->nombres, 'apellidos' => $e->apellidos, 'dni' => $e->dni, 'sueldo_base' => $e->sueldo_base,
            'sistema_pensiones' => 'onp', 'dias_laborables' => 22, 'dias_trabajados' => 22, 'dias_falta' => 0, 'dias_justificados' => 0,
            'total_minutos_tarde' => 0, 'sueldo_proporcional' => $e->sueldo_base, 'remuneracion_bruta' => $e->sueldo_base,
            'porcentaje_pension' => 0.13, 'descuento_pension' => 0, 'total_descuentos' => 0, 'neto_pagar' => $e->sueldo_base,
            'comisiones' => $comisiones, 'importe_horas_extra_diurnas' => $heDiurnas,
        ]);
    }

    // =====================================================================
    // Gratificación
    // =====================================================================

    public function test_a_full_semester_pays_one_salary_plus_nine_percent_extraordinary_bonus(): void
    {
        $g = $this->servicio->calcularGratificacionEmpleado($this->empleado(), $this->company->id, 'julio', 2026);

        $this->assertSame(6, (int) $g->meses_computables);
        $this->assertEquals(3000, $g->remuneracion_computable);
        $this->assertEquals(3000, $g->monto_gratificacion);
        $this->assertEquals(270, $g->bonificacion_extraordinaria);
        $this->assertEquals(3270, $g->monto_total);
        $this->assertSame('2026-07', $g->periodo);
    }

    public function test_a_worker_hired_mid_semester_gets_it_proportional_to_the_months_worked(): void
    {
        $e = $this->empleado(['fecha_ingreso' => '2026-04-10']);   // abril, mayo y junio cuentan

        $g = $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'julio', 2026);

        $this->assertSame(3, (int) $g->meses_computables);
        $this->assertEquals(1500, $g->monto_gratificacion);        // 3000 ÷ 6 × 3
        $this->assertEquals(135, $g->bonificacion_extraordinaria);
    }

    public function test_the_december_gratification_covers_july_to_december(): void
    {
        $e = $this->empleado(['fecha_ingreso' => '2026-10-01']);   // octubre, noviembre, diciembre

        $g = $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'diciembre', 2026);

        $this->assertSame(3, (int) $g->meses_computables);
        $this->assertSame('2026-12', $g->periodo);
    }

    public function test_a_worker_who_left_before_the_semester_or_joined_after_it_gets_nothing(): void
    {
        $retirado = $this->empleado(['fecha_cese' => '2025-12-31']);
        $nuevo = $this->empleado(['fecha_ingreso' => '2026-08-01']);

        $this->assertNull($this->servicio->calcularGratificacionEmpleado($retirado, $this->company->id, 'julio', 2026));
        $this->assertNull($this->servicio->calcularGratificacionEmpleado($nuevo, $this->company->id, 'julio', 2026));
    }

    public function test_family_allowance_is_part_of_the_computable_remuneration(): void
    {
        $g = $this->servicio->calcularGratificacionEmpleado($this->empleado(['aplica_asignacion_familiar' => true]), $this->company->id, 'julio', 2026);

        $this->assertEquals(113, $g->asignacion_familiar);
        $this->assertEquals(3113, $g->remuneracion_computable);
        $this->assertEquals(3113, $g->monto_gratificacion);
    }

    public function test_commissions_are_averaged_only_when_there_were_in_at_least_three_months(): void
    {
        $con3 = $this->empleado();
        foreach (['2026-01', '2026-03', '2026-05'] as $mes) {
            $this->liquidacion($con3, $mes, 1000);
        }
        $con2 = $this->empleado();
        foreach (['2026-01', '2026-03'] as $mes) {
            $this->liquidacion($con2, $mes, 1000);
        }

        $a = $this->servicio->calcularGratificacionEmpleado($con3, $this->company->id, 'julio', 2026);
        $b = $this->servicio->calcularGratificacionEmpleado($con2, $this->company->id, 'julio', 2026);

        $this->assertEquals(500, $a->promedio_comisiones);         // 3 000 ÷ 6
        $this->assertEquals(3500, $a->remuneracion_computable);
        $this->assertEquals(0, $b->promedio_comisiones);           // solo 2 meses: no se promedia
        $this->assertEquals(3000, $b->remuneracion_computable);
    }

    public function test_overtime_is_averaged_with_the_same_three_month_rule(): void
    {
        $e = $this->empleado();
        foreach (['2026-02', '2026-03', '2026-04', '2026-05'] as $mes) {
            $this->liquidacion($e, $mes, 0, 150);
        }

        $g = $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'julio', 2026);

        $this->assertSame(4, (int) $g->meses_con_horas_extra);
        $this->assertEquals(100, $g->promedio_horas_extra);         // 600 ÷ 6
    }

    public function test_recalculating_a_gratification_updates_it_instead_of_duplicating(): void
    {
        $e = $this->empleado();
        $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'julio', 2026);
        $e->update(['sueldo_base' => 4200]);
        $g = $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'julio', 2026);

        $this->assertSame(1, Gratificacion::where('employee_id', $e->id)->count());
        $this->assertEquals(4200, $g->monto_gratificacion);
    }

    // =====================================================================
    // CTS
    // =====================================================================

    public function test_a_full_semester_cts_is_half_a_remuneration_without_gratification(): void
    {
        $c = $this->servicio->calcularCtsEmpleado($this->empleado(), $this->company->id, 'noviembre', 2026);

        $this->assertSame(6, (int) $c->meses_computables);
        $this->assertEquals(3000, $c->remuneracion_computable);
        $this->assertEquals(1500, $c->monto_cts);                    // 3000 ÷ 12 × 6
        $this->assertSame('2026-11', $c->periodo);
    }

    public function test_one_sixth_of_the_gratification_enters_the_cts_computable_remuneration(): void
    {
        $e = $this->empleado();
        $g = $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'julio', 2026);   // monto 3 000

        $c = $this->servicio->calcularCtsEmpleado($e, $this->company->id, 'noviembre', 2026);

        $this->assertEquals(500, $c->sexto_gratificacion);           // 3 000 ÷ 6
        $this->assertEquals(3500, $c->remuneracion_computable);
        $this->assertEquals(1750, $c->monto_cts);                    // 3 500 ÷ 12 × 6
        $this->assertSame($g->id, $c->gratificacion_id);
    }

    public function test_the_may_deposit_uses_the_previous_decembers_gratification(): void
    {
        $e = $this->empleado();
        $this->servicio->calcularGratificacionEmpleado($e, $this->company->id, 'diciembre', 2025);

        $c = $this->servicio->calcularCtsEmpleado($e, $this->company->id, 'mayo', 2026);

        $this->assertEquals(500, $c->sexto_gratificacion);
        $this->assertSame('2026-05', $c->periodo);
    }

    public function test_a_partial_semester_cts_adds_the_proportional_days(): void
    {
        // Ingresa el 16-sep-2026: el semestre mayo–octubre le cuenta 1 mes completo (16-sep → 15-oct) y 16 días
        $e = $this->empleado(['fecha_ingreso' => '2026-09-16']);

        $c = $this->servicio->calcularCtsEmpleado($e, $this->company->id, 'noviembre', 2026);

        $this->assertSame(1, (int) $c->meses_computables);
        $this->assertSame(16, (int) $c->dias_computables);
        $this->assertEquals(round(3000 / 12 * 1 + 3000 / 12 / 30 * 16, 2), $c->monto_cts);   // 250 + 133.33
    }

    public function test_cts_is_not_generated_for_a_worker_outside_the_semester(): void
    {
        $nuevo = $this->empleado(['fecha_ingreso' => '2026-12-01']);

        $this->assertNull($this->servicio->calcularCtsEmpleado($nuevo, $this->company->id, 'noviembre', 2026));
    }

    public function test_the_period_calculation_rejects_an_unknown_cts_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->calcularPeriodoCts($this->company->id, 'marzo', 2026);
    }
}
