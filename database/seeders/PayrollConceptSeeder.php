<?php

namespace Database\Seeders;

use App\Models\PayrollConcept;
use Illuminate\Database\Seeder;

class PayrollConceptSeeder extends Seeder
{
    /**
     * Conceptos genéricos de planilla peruana (régimen general privado).
     * Tasas de ONP/AFP no se aplican aquí — este concepto solo marca qué
     * ingresos son base de cálculo (afecto_onp_afp / afecto_renta_5ta);
     * el monto de la retención lo calcula PlanillaService a partir de
     * AfpTasa o de la tasa ONP fija (13%).
     */
    public function run(): void
    {
        $conceptos = [
            ['codigo' => 'SUELDO_BASICO',      'nombre' => 'Sueldo básico',                'tipo' => 'ingreso',   'afecto_onp_afp' => true,  'afecto_renta_5ta' => true],
            ['codigo' => 'ASIG_FAMILIAR',      'nombre' => 'Asignación familiar',          'tipo' => 'ingreso',   'afecto_onp_afp' => true,  'afecto_renta_5ta' => true],
            ['codigo' => 'HORAS_EXTRA',        'nombre' => 'Horas extra',                  'tipo' => 'ingreso',   'afecto_onp_afp' => true,  'afecto_renta_5ta' => true],
            ['codigo' => 'BONO_ENCARGATURA',   'nombre' => 'Bono por encargatura',         'tipo' => 'ingreso',   'afecto_onp_afp' => true,  'afecto_renta_5ta' => true],
            ['codigo' => 'MOVILIDAD',          'nombre' => 'Movilidad',                    'tipo' => 'ingreso',   'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'COMISIONES',         'nombre' => 'Comisiones',                   'tipo' => 'ingreso',   'afecto_onp_afp' => true,  'afecto_renta_5ta' => true],

            ['codigo' => 'ONP',                'nombre' => 'Retención ONP (Sistema Nacional de Pensiones)', 'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'AFP',                'nombre' => 'Aporte AFP (fondo + comisión + seguro)',        'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'ESSALUD',            'nombre' => 'EsSalud (aporte del empleador, informativo)',   'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'RENTA_5TA',          'nombre' => 'Retención de renta de 5ta categoría',           'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'DESC_TARDANZA',      'nombre' => 'Descuento por tardanzas',                       'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'DESC_INASISTENCIA',  'nombre' => 'Descuento por inasistencias',                   'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'EPS',                'nombre' => 'Descuento EPS (aporte del trabajador)',         'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
            ['codigo' => 'SEGURO_VIDA',        'nombre' => 'Seguro de vida ley',                            'tipo' => 'descuento', 'afecto_onp_afp' => false, 'afecto_renta_5ta' => false],
        ];

        foreach ($conceptos as $c) {
            PayrollConcept::updateOrCreate(
                ['codigo' => $c['codigo']],
                $c + ['active' => true]
            );
        }
    }
}
