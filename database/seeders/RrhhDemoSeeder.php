<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RrhhDemoSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->seedDemo();
        });
    }

    private function seedDemo(): void
    {
        $companyId = DB::table('companies')->first()->id;
        $now = now();

        // ── 1. SEDE ──────────────────────────────────────────────
        $locationId = DB::table('locations')->insertGetId([
            'company_id'  => $companyId,
            'nombre'      => 'Sede Principal - Lima',
            'direccion'   => 'Av. Javier Prado Este 123, San Isidro',
            'reloj_tipo'  => 'zkbio',
            'reloj_activo'=> 1,
            'sync_estado' => 'pendiente',
            'active'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // ── 2. DEPARTAMENTOS ─────────────────────────────────────
        $deptIds = [];
        foreach (['Tecnología', 'Administración', 'Ventas', 'Operaciones', 'Recursos Humanos'] as $dept) {
            $deptIds[$dept] = DB::table('departments')->insertGetId([
                'company_id' => $companyId,
                'nombre'     => $dept,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── 3. HORARIOS ──────────────────────────────────────────
        $scheduleManana = DB::table('schedules')->insertGetId([
            'company_id'         => $companyId,
            'nombre'             => 'Horario Mañana 8-5',
            'hora_entrada'       => '08:00:00',
            'hora_salida'        => '17:00:00',
            'tolerancia_minutos' => 10,
            'refrigerio_inicio'  => '13:00:00',
            'refrigerio_fin'     => '14:00:00',
            'dias_laborables'    => json_encode([1, 2, 3, 4, 5]),
            'es_nocturno'        => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $scheduleTarde = DB::table('schedules')->insertGetId([
            'company_id'         => $companyId,
            'nombre'             => 'Horario Tarde 2-10',
            'hora_entrada'       => '14:00:00',
            'hora_salida'        => '22:00:00',
            'tolerancia_minutos' => 10,
            'refrigerio_inicio'  => '18:00:00',
            'refrigerio_fin'     => '19:00:00',
            'dias_laborables'    => json_encode([1, 2, 3, 4, 5]),
            'es_nocturno'        => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        // ── 4. EMPLEADOS ─────────────────────────────────────────
        // Formato: [nombres, apellidos, dni, codigo, cargo, dept, schedule, fecha_ingreso,
        //           sueldo_base, sistema_pensiones, aplica_5ta_categoria, aplica_comision]
        $sistemasPension = ['onp', 'afp_habitat', 'afp_integra', 'afp_prima', 'afp_profuturo'];

        $empleados = [
            ['CARLOS',   'RODRIGUEZ LOPEZ',     '45123456', 'EMP001', 'Gerente de TI',           $deptIds['Tecnología'],       $scheduleManana, '2022-01-10', 8500, 'afp_habitat', true,  false],
            ['ANA',      'GARCIA TORRES',        '47234567', 'EMP002', 'Desarrollador Senior',    $deptIds['Tecnología'],       $scheduleManana, '2022-03-15', 5200, 'afp_integra', false, false],
            ['LUIS',     'MENDOZA QUISPE',       '48345678', 'EMP003', 'Desarrollador Junior',    $deptIds['Tecnología'],       $scheduleTarde,  '2023-01-05', 3200, 'onp',         false, false],
            ['MARIA',    'FLORES HUANCA',        '49456789', 'EMP004', 'Analista de Sistemas',    $deptIds['Tecnología'],       $scheduleManana, '2022-06-20', 4100, 'afp_prima',   false, false],
            ['PEDRO',    'VASQUEZ MAMANI',       '50567890', 'EMP005', 'Gerente Administrativo',  $deptIds['Administración'],   $scheduleManana, '2021-08-01', 7800, 'afp_profuturo', true, false],
            ['ROSA',     'QUISPE CCALLO',        '51678901', 'EMP006', 'Asistente Contable',      $deptIds['Administración'],   $scheduleManana, '2022-09-12', 2800, 'onp',         false, false],
            ['JORGE',    'LLANOS CHUQUIHUANCA',  '52789012', 'EMP007', 'Jefe de Ventas',          $deptIds['Ventas'],           $scheduleManana, '2021-11-03', 4500, 'afp_habitat', false, true],
            ['PATRICIA', 'SALAS CONDORI',        '53890123', 'EMP008', 'Ejecutiva de Ventas',     $deptIds['Ventas'],           $scheduleManana, '2023-02-14', 2200, 'afp_integra', false, true],
            ['MIGUEL',   'TORRES APAZA',         '54901234', 'EMP009', 'Ejecutivo de Ventas',     $deptIds['Ventas'],           $scheduleTarde,  '2023-04-01', 2200, 'onp',         false, true],
            ['SOFIA',    'PAREDES TITO',         '55012345', 'EMP010', 'Jefe de Operaciones',     $deptIds['Operaciones'],      $scheduleManana, '2021-05-17', 4800, 'afp_prima',   false, false],
            ['HUGO',     'CHAVEZ PUMA',          '56123456', 'EMP011', 'Técnico de Soporte',      $deptIds['Operaciones'],      $scheduleTarde,  '2022-07-22', 2500, 'onp',         false, false],
            ['DIANA',    'CANO HUAYTA',          '57234567', 'EMP012', 'Jefa de RRHH',            $deptIds['Recursos Humanos'], $scheduleManana, '2021-03-08', 5500, 'afp_profuturo', false, false],
        ];

        $empIds = [];
        foreach ($empleados as $emp) {
            $empIds[] = DB::table('employees')->insertGetId([
                'company_id'            => $companyId,
                'location_id'           => $locationId,
                'department_id'         => $emp[5],
                'schedule_id'           => $emp[6],
                'nombres'               => $emp[0],
                'apellidos'             => $emp[1],
                'dni'                   => $emp[2],
                'codigo_empleado'       => $emp[3],
                'cargo'                 => $emp[4],
                'fecha_ingreso'         => $emp[7],
                'sueldo_base'           => $emp[8],
                'sistema_pensiones'     => $emp[9],
                'aplica_5ta_categoria'  => $emp[10],
                'aplica_comision'       => $emp[11],
                'active'                => 1,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        // ── 5. REGISTROS DE ASISTENCIA (últimos 30 días hábiles) ─
        $startDate = Carbon::now()->subDays(30);
        $endDate   = Carbon::now()->subDay();

        foreach ($empIds as $empId) {
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                if ($current->isWeekend()) {
                    $current->addDay();
                    continue;
                }

                $rand = rand(1, 100);

                if ($rand <= 93) {
                    $minutosTarde = $rand <= 85 ? 0 : rand(5, 25);
                    $entradaHour  = 8;
                    $entradaMin   = $minutosTarde > 0 ? $minutosTarde : rand(0, 5);

                    $entrada = $current->copy()->setTime($entradaHour, $entradaMin, 0);
                    $salida  = $current->copy()->setTime(17, rand(0, 30), 0);

                    $minutosTrabajados = $entrada->diffInMinutes($salida);

                    DB::table('attendance_records')->insert([
                        'company_id'         => $companyId,
                        'employee_id'        => $empId,
                        'location_id'        => $locationId,
                        'fecha'              => $current->toDateString(),
                        'hora_entrada'       => $entrada->toDateTimeString(),
                        'fuente_entrada'     => 'biometrico',
                        'hora_salida'        => $salida->toDateTimeString(),
                        'fuente_salida'      => 'biometrico',
                        'minutos_tarde'      => $minutosTarde,
                        'minutos_trabajados' => $minutosTrabajados,
                        'horas_ordinarias'   => min(8, round($minutosTrabajados / 60, 2)),
                        'horas_extra_diurnas'=> max(0, round(($minutosTrabajados - 480) / 60, 2)),
                        'estado'             => $minutosTarde > 0 ? 'tarde' : 'presente',
                        'justificado'        => 0,
                        'corregido_manualmente' => 0,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ]);
                }

                $current->addDay();
            }
        }

        // ── 6. COMISIONES DEMO (para los 3 empleados con aplica_comision) ──
        // OJO: comision_uploads NO tiene company_id, y comision_detalles matchea
        // por el campo de texto 'vendedor' (nombre), no por employee_id — así es
        // como está construido el módulo real, no es un descuido del seeder.
        if (\Illuminate\Support\Facades\Schema::hasTable('comision_uploads')) {
            $periodoActual = now()->format('Y-m');
            $mesNombre     = now()->locale('es')->isoFormat('MMMM YYYY');

            $uploadId = DB::table('comision_uploads')->insertGetId([
                'periodo'             => $periodoActual,
                'mes_nombre'          => $mesNombre,
                'archivo_cobranzas'   => 'demo_cobranzas_' . $periodoActual . '.xlsx',
                'archivo_comisiones'  => 'demo_comisiones_' . $periodoActual . '.xlsx',
                'estado'              => 'completado',
                'total_facturas'      => 3,
                'total_cobradas'      => 3,
                'total_pendientes'    => 0,
                'total_anuladas'      => 0,
                'total_base_cobrada'  => 18800.00,
                'total_comision'      => 1880.00,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // Jorge, Patricia y Miguel son los vendedores (índices 6, 7, 8 del array $empleados).
            // El campo 'vendedor' debe calzar EXACTO con "nombres apellidos" del empleado,
            // tal como espera el matching real del módulo de comisiones.
            $vendedores = [
                $empIds[6] => ['nombre' => 'JORGE LLANOS CHUQUIHUANCA',  'monto' => 850.00],
                $empIds[7] => ['nombre' => 'PATRICIA SALAS CONDORI',      'monto' => 420.00],
                $empIds[8] => ['nombre' => 'MIGUEL TORRES APAZA',         'monto' => 610.00],
            ];

            foreach ($vendedores as $empId => $v) {
                DB::table('comision_detalles')->insert([
                    'comision_upload_id'   => $uploadId,
                    'periodo'              => $periodoActual,
                    'vendedor'             => $v['nombre'],
                    'numdoc'               => 'F001-' . str_pad($empId, 4, '0', STR_PAD_LEFT),
                    'tipo_doc'             => 'FACTURA',
                    'razon_social'         => 'Cliente Demo ' . $empId,
                    'condicion'            => 'CREDITO',
                    'fecha_emision'        => $now->copy()->subDays(20)->toDateString(),
                    'moneda'               => 'PEN',
                    'base_comision_venta'  => $v['monto'] * 10,
                    'base_comision_cobrada'=> $v['monto'] * 10,
                    'fecha_pago'           => $now->copy()->subDays(5)->toDateString(),
                    'importe_cobrado'      => $v['monto'] * 10,
                    'estado'               => 'cobrada',
                    'mes_cobro'            => $periodoActual,
                    'comision_calculada'   => $v['monto'],
                    'porcentaje_comision'  => 0.10, // 10%, formato fracción (decimal 5,4)
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }

        $this->command->info('✅ Demo RRHH creada:');
        $this->command->info('   · 1 sede, 5 departamentos, 2 horarios');
        $this->command->info('   · 12 empleados activos con sueldo, AFP/ONP variado');
        $this->command->info('   · 3 empleados con comisiones demo');
        $this->command->info('   · Registros de asistencia últimos 30 días');
    }
}