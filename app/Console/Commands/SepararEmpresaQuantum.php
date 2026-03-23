<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class SepararEmpresaQuantum extends Command
{
    protected $signature   = 'sumarh:separar-quantum {--force : Ejecutar sin confirmación}';
    protected $description = 'Crea la empresa Quantum y reasigna sus empleados y departamento';

    public function handle(): int
    {
        $this->info('=== Separación de empresa Quantum ===');
        $this->newLine();

        // 1. Mostrar estado actual
        $inprocess = Company::find(2);
        $this->info("Empresa origen: [{$inprocess->id}] {$inprocess->razon_social}");

        $deptQuantum = Department::find(2);
        $empleadosQuantum = Employee::where('department_id', 2)->get();

        $this->info("Departamento a migrar: [{$deptQuantum->id}] {$deptQuantum->nombre}");
        $this->info("Empleados a reasignar: {$empleadosQuantum->count()}");
        $this->newLine();

        $this->table(
            ['ID', 'Nombre', 'Apellidos'],
            $empleadosQuantum->map(fn($e) => [$e->id, $e->nombres, $e->apellidos])
        );

        $this->newLine();

        if (!$this->option('force') && !$this->confirm('¿Confirmas la operación?')) {
            $this->warn('Operación cancelada.');
            return 0;
        }

        DB::transaction(function () use ($inprocess, $deptQuantum, $empleadosQuantum) {

            // 2. Crear empresa Quantum (o recuperarla si ya existe)
            $quantum = Company::firstOrCreate(
                ['ruc' => '00000000000'], // RUC temporal — actualizar luego
                [
                    'razon_social' => 'QUANTUM',
                    'direccion'    => $inprocess->direccion,
                    'telefono'     => $inprocess->telefono,
                    'email'        => $inprocess->email,
                    'active'       => true,
                ]
            );
            $this->info("✓ Empresa creada/encontrada: [{$quantum->id}] {$quantum->razon_social}");

            // 3. Mover el departamento Quantum a la nueva empresa
            $deptQuantum->update(['company_id' => $quantum->id]);
            $this->info("✓ Departamento [{$deptQuantum->nombre}] reasignado a Quantum");

            // 4. Reasignar empleados
            foreach ($empleadosQuantum as $emp) {
                $emp->update(['company_id' => $quantum->id]);
                $this->line("  → {$emp->apellidos}, {$emp->nombres}");
            }
            $this->info("✓ {$empleadosQuantum->count()} empleados reasignados");
        });

        $this->newLine();
        $this->info('✅ Listo. Recuerda actualizar el RUC real de Quantum en el panel admin.');

        return 0;
    }
}
