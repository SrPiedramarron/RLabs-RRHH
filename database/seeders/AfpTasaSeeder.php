<?php

namespace Database\Seeders;

use App\Models\AfpTasa;
use Illuminate\Database\Seeder;

class AfpTasaSeeder extends Seeder
{
    /**
     * Tasas vigentes 2026 (fuente: SBS / Asociación AFP Perú).
     * Prima de seguro bajó de 1.84% a 1.37% por Ley 32123 desde enero 2026.
     * Tope de remuneración asegurable: S/ 12,209.11 (verificar trimestralmente en sbs.gob.pe).
     */
    public function run(): void
    {
        $tasas = [
            ['afp' => 'afp_habitat',   'comision_flujo' => 0.0147],
            ['afp' => 'afp_integra',   'comision_flujo' => 0.0155],
            ['afp' => 'afp_prima',     'comision_flujo' => 0.0160],
            ['afp' => 'afp_profuturo', 'comision_flujo' => 0.0169],
        ];

        foreach ($tasas as $t) {
            AfpTasa::updateOrCreate(
                ['afp' => $t['afp'], 'vigente_desde' => '2026-01-01'],
                [
                    'comision_flujo'               => $t['comision_flujo'],
                    'prima_seguro'                  => 0.0137,
                    'aporte_obligatorio'            => 0.1000,
                    'tope_remuneracion_asegurable'  => 12209.11,
                    'vigente_hasta'                 => null,
                ]
            );
        }
    }
}
