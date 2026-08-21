<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        AttendanceRecord::truncate();
        AttendanceLog::truncate();
        Employee::truncate();
        Schedule::truncate();
        Location::truncate();
        Company::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $empresas = [
            ['razon_social' => 'TECH SOLUTIONS SAC',      'ruc' => '20100000001'],
            ['razon_social' => 'INVERSIONES ANDINAS SRL', 'ruc' => '20100000002'],
            ['razon_social' => 'COMERCIAL DEL PACIFICO SA','ruc' => '20100000003'],
        ];

        $sedesNombres = [
            ['SEDE CENTRAL', 'SEDE SUR'],
            ['OFICINA PRINCIPAL', 'SUCURSAL NORTE'],
            ['LOCAL MIRAFLORES', 'LOCAL SAN ISIDRO'],
        ];

        $horariosData = [
            ['nombre' => 'Horario General',    'entrada' => '08:00', 'salida' => '17:00'],
            ['nombre' => 'Horario Reducido',   'entrada' => '09:00', 'salida' => '16:00'],
            ['nombre' => 'Horario Extendido',  'entrada' => '07:00', 'salida' => '18:00'],
        ];

        $nombresM = ['CARLOS','LUIS','JUAN','PEDRO','MIGUEL','JORGE','ANDRES','ROBERTO','MARIO','DIEGO',
                     'FRANCISCO','ALBERTO','SERGIO','RAUL','VICTOR'];
        $nombresF = ['MARIA','ANA','LUCIA','ROSA','CARMEN','PATRICIA','SANDRA','MONICA','ELENA','SOFIA',
                     'ANDREA','JESSICA','VALERIA','DANIELA','CLAUDIA'];
        $apellidos = ['GARCIA','RODRIGUEZ','LOPEZ','MARTINEZ','GONZALEZ','PEREZ','SANCHEZ','RAMIREZ',
                      'TORRES','FLORES','RIVERA','GOMEZ','DIAZ','REYES','MORALES'];

        foreach ($empresas as $idx => $empData) {
            $company = Company::create([
                'razon_social'    => $empData['razon_social'],
                'ruc'             => $empData['ruc'],
                'active'          => true,
            ]);

            // Horarios
            $horarios = [];
            foreach ($horariosData as $h) {
                $horarios[] = Schedule::create([
                    'company_id'         => $company->id,
                    'nombre'             => $h['nombre'],
                    'hora_entrada'       => $h['entrada'],
                    'hora_salida'        => $h['salida'],
                    'tolerancia_minutos' => 10,
                    'dias_laborables'    => [1,2,3,4,5],
                ]);
            }

            // Sedes
            $sedes = [];
            foreach ($sedesNombres[$idx] as $sedeName) {
                $sedes[] = Location::create([
                    'company_id'  => $company->id,
                    'nombre'      => $sedeName,
                    'reloj_activo'=> false,
                    'active'      => true,
                ]);
            }

            // Empleados — 10 por sede
            $empNum = 1;
            foreach ($sedes as $sede) {
                for ($i = 0; $i < 10; $i++) {
                    $esM      = rand(0, 1);
                    $nombre   = $esM ? $nombresM[array_rand($nombresM)] : $nombresF[array_rand($nombresF)];
                    $apellido = $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
                    $dni      = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
                    $schedule = $horarios[array_rand($horarios)];

                    $employee = Employee::create([
                        'company_id'    => $company->id,
                        'location_id'   => $sede->id,
                        'schedule_id'   => $schedule->id,
                        'nombres'       => $nombre,
                        'apellidos'     => $apellido,
                        'dni'           => $dni,
                        'codigo_empleado'=> 'EMP' . str_pad($empNum++, 4, '0', STR_PAD_LEFT),
                        'reloj_id'      => $dni,
                        'fecha_ingreso' => now()->subMonths(rand(1, 24))->toDateString(),
                        'active'        => true,
                    ]);

                    // Registros de asistencia últimos 30 días
                    for ($d = 30; $d >= 1; $d--) {
                        $fecha = Carbon::now()->subDays($d);

                        // Saltar fines de semana
                        if ($fecha->isWeekend()) continue;

                        // 10% de ausencias
                        if (rand(1, 10) === 1) continue;

                        $entradaBase = Carbon::parse($fecha->format('Y-m-d') . ' ' . $schedule->hora_entrada);
                        $salidaBase  = Carbon::parse($fecha->format('Y-m-d') . ' ' . $schedule->hora_salida);

                        // Variación de ±20 minutos
                        $entrada = $entradaBase->copy()->addMinutes(rand(-5, 20));
                        $salida  = $salidaBase->copy()->addMinutes(rand(-10, 30));

                        AttendanceLog::create([
                            'location_id' => $sede->id,
                            'reloj_id'    => $dni,
                            'reloj_uid'   => 0,
                            'timestamp'   => $entrada,
                            'tipo'        => 0,
                            'estado'      => 0,
                            'procesado'   => false,
                            'created_at'  => $entrada,
                        ]);

                        AttendanceLog::create([
                            'location_id' => $sede->id,
                            'reloj_id'    => $dni,
                            'reloj_uid'   => 0,
                            'timestamp'   => $salida,
                            'tipo'        => 1,
                            'estado'      => 0,
                            'procesado'   => false,
                            'created_at'  => $salida,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Demo data creada exitosamente!');
    }
}