<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaysSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar feriados nacionales existentes
        Holiday::whereNull('company_id')->delete();

        $feriados = [
            // 2025
            ['fecha' => '2025-01-01', 'nombre' => 'Año Nuevo'],
            ['fecha' => '2025-04-17', 'nombre' => 'Jueves Santo'],
            ['fecha' => '2025-04-18', 'nombre' => 'Viernes Santo'],
            ['fecha' => '2025-04-19', 'nombre' => 'Sábado de Gloria'],
            ['fecha' => '2025-05-01', 'nombre' => 'Día del Trabajo'],
            ['fecha' => '2025-06-07', 'nombre' => 'Batalla de Arica'],
            ['fecha' => '2025-06-29', 'nombre' => 'San Pedro y San Pablo'],
            ['fecha' => '2025-07-23', 'nombre' => 'Día de la Fuerza Aérea del Perú'],
            ['fecha' => '2025-07-28', 'nombre' => 'Fiestas Patrias'],
            ['fecha' => '2025-07-29', 'nombre' => 'Fiestas Patrias'],
            ['fecha' => '2025-08-06', 'nombre' => 'Batalla de Junín'],
            ['fecha' => '2025-08-30', 'nombre' => 'Santa Rosa de Lima'],
            ['fecha' => '2025-10-08', 'nombre' => 'Combate de Angamos'],
            ['fecha' => '2025-11-01', 'nombre' => 'Día de Todos los Santos'],
            ['fecha' => '2025-12-08', 'nombre' => 'Inmaculada Concepción'],
            ['fecha' => '2025-12-09', 'nombre' => 'Batalla de Ayacucho'],
            ['fecha' => '2025-12-25', 'nombre' => 'Navidad'],

            // 2026
            ['fecha' => '2026-01-01', 'nombre' => 'Año Nuevo'],
            ['fecha' => '2026-04-02', 'nombre' => 'Jueves Santo'],
            ['fecha' => '2026-04-03', 'nombre' => 'Viernes Santo'],
            ['fecha' => '2026-04-04', 'nombre' => 'Sábado de Gloria'],
            ['fecha' => '2026-05-01', 'nombre' => 'Día del Trabajo'],
            ['fecha' => '2026-06-07', 'nombre' => 'Batalla de Arica'],
            ['fecha' => '2026-06-29', 'nombre' => 'San Pedro y San Pablo'],
            ['fecha' => '2026-07-23', 'nombre' => 'Día de la Fuerza Aérea del Perú'],
            ['fecha' => '2026-07-28', 'nombre' => 'Fiestas Patrias'],
            ['fecha' => '2026-07-29', 'nombre' => 'Fiestas Patrias'],
            ['fecha' => '2026-08-06', 'nombre' => 'Batalla de Junín'],
            ['fecha' => '2026-08-30', 'nombre' => 'Santa Rosa de Lima'],
            ['fecha' => '2026-10-08', 'nombre' => 'Combate de Angamos'],
            ['fecha' => '2026-11-01', 'nombre' => 'Día de Todos los Santos'],
            ['fecha' => '2026-12-08', 'nombre' => 'Inmaculada Concepción'],
            ['fecha' => '2026-12-09', 'nombre' => 'Batalla de Ayacucho'],
            ['fecha' => '2026-12-25', 'nombre' => 'Navidad'],
        ];

        foreach ($feriados as $feriado) {
            Holiday::create([
                'company_id' => null,
                'fecha'      => $feriado['fecha'],
                'nombre'     => $feriado['nombre'],
                'tipo'       => 'nacional',
            ]);
        }

        $this->command->info('✅ ' . count($feriados) . ' feriados nacionales cargados (2025-2026).');
    }
}