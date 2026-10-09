<?php

namespace Tests\Feature;

use App\Models\AfpTasa;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\ParametroLegal;
use App\Models\PlanillaLiquidacion;
use App\Models\Schedule;
use App\Services\PlanillaService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Planilla mensual (PlanillaService::calcularEmpleado). Las expectativas se calculan a mano a partir de
 * las reglas documentadas en el servicio (RMV/ONP/AFP desde tablas con vigencia, corte de asistencia del
 * 26 al 25, EsSalud 9 %, etc.), no copiando la fórmula del código.
 *
 * Periodo de prueba: septiembre 2026 → asistencia del 26-ago al 25-sep.
 */
class PlanillaMensualTest extends TestCase
{
    use RefreshDatabase;

    private const PERIODO = '2026-09';

    private const RMV = 1130.0;

    private Company $company;

    private Location $location;

    private Schedule $schedule;

    private PlanillaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        ParametroLegal::create(['clave' => ParametroLegal::RMV, 'descripcion' => 'RMV', 'valor' => self::RMV, 'vigente_desde' => '2026-01-01']);
        ParametroLegal::create(['clave' => ParametroLegal::TASA_ONP, 'descripcion' => 'ONP', 'valor' => 0.13, 'vigente_desde' => '2026-01-01']);
        AfpTasa::create([
            'afp' => 'afp_habitat', 'vigente_desde' => '2026-01-01', 'comision_flujo' => 0.0147, 'prima_seguro' => 0.0137,
            'aporte_obligatorio' => 0.10, 'tope_remuneracion_asegurable' => 12209.11,
        ]);

        $this->company = Company::create(['razon_social' => 'EMPRESA TEST SAC', 'ruc' => '20100000001', 'active' => true]);
        $this->location = Location::create(['company_id' => $this->company->id, 'nombre' => 'Sede Central', 'active' => true]);
        $this->schedule = Schedule::create([
            'company_id' => $this->company->id, 'nombre' => 'General', 'hora_entrada' => '08:00', 'hora_salida' => '17:00',
            'tolerancia_minutos' => 10, 'dias_laborables' => [1, 2, 3, 4, 5],
        ]);

        $this->servicio = app(PlanillaService::class);
    }

    private function empleado(array $attrs = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'location_id' => $this->location->id, 'schedule_id' => $this->schedule->id,
            'nombres' => 'JUAN', 'apellidos' => 'PEREZ TEST', 'dni' => (string) random_int(10000000, 99999999), 'cargo' => 'Analista',
            'fecha_ingreso' => '2024-01-10', 'sueldo_base' => 3000, 'sistema_pensiones' => 'onp',
            'aplica_5ta_categoria' => false, 'aplica_comision_flujo_afp' => true, 'active' => true,
        ], $attrs));
    }

    private function dia(Employee $e, string $fecha, string $estado, array $extra = []): AttendanceRecord
    {
        return AttendanceRecord::create(array_merge([
            'employee_id' => $e->id, 'company_id' => $this->company->id, 'fecha' => $fecha, 'estado' => $estado,
        ], $extra));
    }

    private function calcular(Employee $e, array $extra = []): PlanillaLiquidacion
    {
        return $this->servicio->calcularEmpleado($e, $this->company->id, self::PERIODO, 2026, 9, ...$extra);
    }

    /** Días hábiles (lun-vie) entre el 26-ago y el 25-sep de 2026, contados de forma independiente. */
    private function diasHabilesDelPeriodo(): int
    {
        return collect(CarbonPeriod::create('2026-08-26', '2026-09-25'))->filter(fn (Carbon $d) => $d->isWeekday())->count();
    }

    // =====================================================================
    // Sueldo, pensión y aportes
    // =====================================================================

    public function test_a_full_month_pays_the_base_salary_with_onp_and_employer_essalud(): void
    {
        $p = $this->calcular($this->empleado(['sueldo_base' => 3000]));

        $this->assertEquals(3000, $p->remuneracion_bruta);
        $this->assertEquals(390, $p->descuento_pension);       // ONP 13 %
        $this->assertEquals(2610, $p->neto_pagar);
        $this->assertEquals(270, $p->essalud_empleador);       // 9 % a cargo del empleador
        $this->assertSame('SEPTIEMBRE 2026', $p->mes_nombre);
    }

    public function test_afp_discounts_mandatory_contribution_flow_commission_and_insurance(): void
    {
        $p = $this->calcular($this->empleado(['sistema_pensiones' => 'afp_habitat']));

        $this->assertEquals(300.00, $p->afp_aporte_obligatorio);   // 10 %
        $this->assertEquals(44.10, $p->afp_comision_flujo);        // 1.47 %
        $this->assertEquals(41.10, $p->afp_prima_seguro);          // 1.37 %
        $this->assertEquals(385.20, $p->descuento_pension);
        $this->assertEquals(3000 - 385.20, $p->neto_pagar);
    }

    public function test_afp_flow_commission_is_skipped_when_the_worker_has_the_mixed_commission_off(): void
    {
        $p = $this->calcular($this->empleado(['sistema_pensiones' => 'afp_habitat', 'aplica_comision_flujo_afp' => false]));

        $this->assertEquals(0, $p->afp_comision_flujo);
        $this->assertEquals(341.10, $p->descuento_pension);   // 300 + 41.10
    }

    public function test_the_afp_insurance_premium_is_capped_at_the_maximum_insurable_salary(): void
    {
        $p = $this->calcular($this->empleado(['sistema_pensiones' => 'afp_habitat', 'sueldo_base' => 20000]));

        $this->assertEquals(2000.00, $p->afp_aporte_obligatorio);
        $this->assertEquals(round(12209.11 * 0.0137, 2), $p->afp_prima_seguro);   // prima sobre el tope, no sobre 20 000
    }

    public function test_afp_without_a_configured_rate_stops_the_calculation(): void
    {
        $e = $this->empleado(['sistema_pensiones' => 'afp_prima']);   // solo existe la tasa de Habitat

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('afp_prima');
        $this->calcular($e);
    }

    public function test_essalud_is_computed_on_the_minimum_wage_when_the_salary_is_lower(): void
    {
        $p = $this->calcular($this->empleado(['sueldo_base' => 900]));

        $this->assertEquals(round(self::RMV * 0.09, 2), $p->essalud_empleador);   // 101.70, no 81.00
    }

    public function test_a_missing_legal_parameter_stops_the_calculation_with_a_clear_message(): void
    {
        ParametroLegal::where('clave', ParametroLegal::TASA_ONP)->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tasa_onp');
        $this->calcular($this->empleado());
    }

    // =====================================================================
    // Asistencia: faltas, tardanzas, horas extra
    // =====================================================================

    public function test_unjustified_absences_discount_one_thirtieth_of_the_salary_each(): void
    {
        $e = $this->empleado(['sueldo_base' => 3000]);
        $this->dia($e, '2026-09-02', 'ausente');
        $this->dia($e, '2026-09-03', 'ausente');
        $this->dia($e, '2026-09-04', 'ausente', ['justificado' => true]);   // justificada: no descuenta

        $p = $this->calcular($e);

        $this->assertSame(2, (int) $p->dias_falta);
        $this->assertEquals(200, $p->descuento_faltas);
        $this->assertEquals(2800, $p->sueldo_proporcional);
        $this->assertEquals(2800, $p->remuneracion_bruta);
    }

    public function test_attendance_outside_the_26th_to_25th_cutoff_is_ignored(): void
    {
        $e = $this->empleado();
        $this->dia($e, '2026-08-25', 'ausente');   // antes del corte
        $this->dia($e, '2026-09-26', 'ausente');   // después del corte
        $this->dia($e, '2026-08-26', 'ausente');   // primer día del periodo: sí cuenta
        $this->dia($e, '2026-09-25', 'ausente');   // último día del periodo: sí cuenta

        $p = $this->calcular($e);

        $this->assertSame(2, (int) $p->dias_falta);
    }

    public function test_lateness_is_discounted_per_minute(): void
    {
        $e = $this->empleado(['sueldo_base' => 3000]);                       // minuto = 3000/30/8/60
        $this->dia($e, '2026-09-02', 'tarde', ['minutos_tarde' => 60]);
        $this->dia($e, '2026-09-03', 'tarde', ['minutos_tarde' => 30]);

        $p = $this->calcular($e);

        $this->assertSame(90, (int) $p->total_minutos_tarde);
        $this->assertEquals(18.75, $p->descuento_tardanzas);
        $this->assertEquals(2981.25, $p->remuneracion_bruta);
    }

    public function test_workers_exempt_from_attendance_registration_never_get_absence_or_lateness_discounts(): void
    {
        $e = $this->empleado(['exonerado_registro' => true]);
        $this->dia($e, '2026-09-02', 'ausente');
        $this->dia($e, '2026-09-03', 'tarde', ['minutos_tarde' => 120]);

        $p = $this->calcular($e);

        $this->assertSame(0, (int) $p->dias_falta);
        $this->assertEquals(0, $p->descuento_faltas);
        $this->assertEquals(0, $p->descuento_tardanzas);
        $this->assertEquals(3000, $p->remuneracion_bruta);
    }

    public function test_overtime_is_paid_with_25_and_35_percent_surcharges(): void
    {
        $e = $this->empleado(['sueldo_base' => 3000]);                       // hora = 3000/30/8 = 12.50
        $this->dia($e, '2026-09-02', 'presente', ['horas_extra_diurnas' => 2, 'horas_extra_nocturnas' => 0]);
        $this->dia($e, '2026-09-03', 'presente', ['horas_extra_diurnas' => 1, 'horas_extra_nocturnas' => 2]);

        $p = $this->calcular($e);

        $this->assertEquals(46.88, $p->importe_horas_extra_diurnas);        // 3 h × 12.50 × 1.25 = 46.875
        $this->assertEquals(33.75, $p->importe_horas_extra_nocturnas);      // 2 h × 12.50 × 1.35
        $this->assertEquals(round(3000 + 46.875 + 33.75, 2), $p->remuneracion_bruta);
        $this->assertEquals(3.0, (float) $p->horas_extra_diurnas);
    }

    public function test_compensated_overtime_is_recorded_but_not_paid(): void
    {
        $e = $this->empleado(['compensa_horas_extras' => true]);
        $this->dia($e, '2026-09-02', 'presente', ['horas_extra_diurnas' => 3]);

        $p = $this->calcular($e);

        $this->assertEquals(3.0, (float) $p->horas_extra_diurnas);
        $this->assertEquals(0, $p->importe_horas_extra_diurnas);
        $this->assertEquals(3000, $p->remuneracion_bruta);
    }

    // =====================================================================
    // Vacaciones, asignación familiar y bonos
    // =====================================================================

    public function test_vacation_days_are_moved_from_salary_to_vacation_pay_without_changing_the_gross(): void
    {
        $e = $this->empleado(['sueldo_base' => 3000]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'] as $d) {
            $this->dia($e, $d, 'vacaciones');
        }

        $p = $this->calcular($e);

        $this->assertSame(5, (int) $p->dias_vacaciones);
        $this->assertEquals(2500, $p->sueldo_proporcional);
        $this->assertEquals(500, $p->vacaciones);                 // 3000 / 30 × 5
        $this->assertEquals(3000, $p->remuneracion_bruta);
    }

    public function test_family_allowance_is_ten_percent_of_the_minimum_wage_and_part_of_vacation_pay(): void
    {
        $sin = $this->calcular($this->empleado());
        $this->assertEquals(0, $sin->asignacion_familiar);

        $e = $this->empleado(['aplica_asignacion_familiar' => true]);
        $this->dia($e, '2026-09-07', 'vacaciones');
        $p = $this->calcular($e);

        $this->assertEquals(113.00, $p->asignacion_familiar);                       // 10 % de 1 130
        $this->assertEquals(round((3000 + 113) / 30, 2), $p->vacaciones);           // la asignación entra a la remuneración vacacional
        $this->assertEquals(round(3000 - 100 + 113 + (3000 + 113) / 30, 2), $p->remuneracion_bruta);
    }

    public function test_the_commuting_allowance_is_prorated_by_attendance_and_stays_out_of_the_gross(): void
    {
        $e = $this->empleado(['movilidad_mensual_maxima' => 300]);
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-07'] as $d) {
            $this->dia($e, $d, 'presente');
        }
        $this->dia($e, '2026-09-08', 'tarde', ['minutos_tarde' => 0]);   // "tarde" también cuenta como día trabajado

        $p = $this->calcular($e);

        $esperado = round(300 / $this->diasHabilesDelPeriodo() * 6, 2);
        $this->assertSame(6, (int) $p->dias_trabajados);
        $this->assertEquals($esperado, $p->bono_movilidad);
        $this->assertEquals(3000, $p->remuneracion_bruta);                          // no afecta EsSalud ni pensión
        $this->assertEquals(round(3000 - 390 + $esperado, 2), $p->neto_pagar);
    }

    public function test_the_supervision_bonus_is_part_of_the_gross_and_is_subject_to_pension(): void
    {
        $p = $this->calcular($this->empleado(['bono_encargatura' => 200]));

        $this->assertEquals(3200, $p->remuneracion_bruta);
        $this->assertEquals(416, $p->descuento_pension);       // 13 % de 3 200
    }

    // =====================================================================
    // Neto, descuentos manuales y recálculo
    // =====================================================================

    public function test_net_pay_subtracts_manual_discounts_and_advances_and_adds_subsidies(): void
    {
        $p = $this->calcular($this->empleado(), [0, 50, 200, 100, 0]);   // bono, otro descuento, adelanto, sub. enfermedad, sub. maternidad

        // bruto 3000 − ONP 390 − otros 50 − adelanto 200 + subsidio 100
        $this->assertEquals(2460, $p->neto_pagar);
        $this->assertEquals(390, $p->total_descuentos);
    }

    public function test_a_special_bonus_adds_to_the_gross(): void
    {
        $p = $this->calcular($this->empleado(), [500]);

        $this->assertEquals(3500, $p->remuneracion_bruta);
        $this->assertEquals(455, $p->descuento_pension);
    }

    public function test_recalculating_keeps_the_manual_amounts_loaded_before(): void
    {
        $e = $this->empleado();
        $this->calcular($e, [500, 50, 200]);

        $p = $this->calcular($e);   // se recalcula sin volver a escribir los montos manuales

        $this->assertEquals(500, $p->bonos_especiales);
        $this->assertEquals(50, $p->otros_descuentos);
        $this->assertEquals(200, $p->adelanto);
        $this->assertSame(1, PlanillaLiquidacion::where('employee_id', $e->id)->count());
    }

    public function test_a_manual_fifth_category_withholding_overrides_the_calculation_and_zero_is_valid(): void
    {
        $e = $this->empleado(['aplica_5ta_categoria' => true]);

        $conRetencion = $this->calcular($e, [0, 0, 0, 0, 0, 150.0]);
        $this->assertEquals(150, $conRetencion->descuento_5ta_categoria);
        $this->assertEquals(390 + 150, $conRetencion->total_descuentos);

        $enCero = $this->calcular($e, [0, 0, 0, 0, 0, 0.0]);
        $this->assertEquals(0, $enCero->descuento_5ta_categoria);

        $sinCambios = $this->calcular($e);   // sin valor nuevo: conserva el override guardado (0)
        $this->assertEquals(0, $sinCambios->descuento_5ta_categoria);
    }

    public function test_legal_parameters_are_read_by_effective_date(): void
    {
        // Subida de la RMV en octubre: septiembre sigue calculándose con el valor anterior
        ParametroLegal::where('clave', ParametroLegal::RMV)->update(['vigente_hasta' => '2026-09-30']);
        ParametroLegal::create(['clave' => ParametroLegal::RMV, 'descripcion' => 'RMV nueva', 'valor' => 1200, 'vigente_desde' => '2026-10-01']);

        $septiembre = $this->calcular($this->empleado(['aplica_asignacion_familiar' => true, 'sueldo_base' => 900]));

        $this->assertEquals(113.00, $septiembre->asignacion_familiar);   // 10 % de 1 130 (vigente en septiembre)
        $this->assertEquals(round(self::RMV * 0.09, 2), $septiembre->essalud_empleador);

        $octubre = $this->servicio->calcularEmpleado($this->empleado(['aplica_asignacion_familiar' => true, 'sueldo_base' => 900]), $this->company->id, '2026-10', 2026, 10);
        $this->assertEquals(120.00, $octubre->asignacion_familiar);       // 10 % de 1 200
    }
}
