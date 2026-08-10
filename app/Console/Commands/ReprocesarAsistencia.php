<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReprocesarAsistencia extends Command
{
    protected $signature = 'asistencia:reprocesar
                            {--desde= : Fecha inicio (Y-m-d), por defecto 2026-05-01}
                            {--hasta= : Fecha fin (Y-m-d), por defecto hoy}
                            {--company= : ID de empresa (opcional, procesa todas si no se indica)}
                            {--dry-run : Solo muestra cuántos registros se afectarían, sin modificar}';

    protected $description = 'Reprocesa los registros de asistencia en un rango de fechas usando los logs del reloj';

    public function handle(AttendanceProcessor $processor): int
    {
        $desde     = $this->option('desde') ?? '2026-05-01';
        $hasta     = $this->option('hasta') ?? now()->toDateString();
        $companyId = $this->option('company');
        $dryRun    = $this->option('dry-run');

        // Validar fechas
        try {
            $desdeCarbon = Carbon::parse($desde);
            $hastaCarbon = Carbon::parse($hasta);
        } catch (\Exception $e) {
            $this->error("Fecha inválida: {$e->getMessage()}");
            return self::FAILURE;
        }

        if ($desdeCarbon->gt($hastaCarbon)) {
            $this->error('La fecha de inicio debe ser anterior a la fecha fin.');
            return self::FAILURE;
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Reprocesamiento de Asistencia");
        $this->info("  Período : {$desdeCarbon->format('d/m/Y')} → {$hastaCarbon->format('d/m/Y')}");
        $this->info("  Empresa : " . ($companyId ? "ID {$companyId}" : 'Todas'));
        $this->info("  Modo    : " . ($dryRun ? '🔍 DRY RUN (sin cambios)' : '✏️  ESCRITURA REAL'));
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Obtener combinaciones únicas reloj_id + fecha que tienen logs en el rango
        $query = AttendanceLog::whereBetween('timestamp', [
                $desdeCarbon->startOfDay()->toDateTimeString(),
                $hastaCarbon->copy()->endOfDay()->toDateTimeString(),
            ])
            ->selectRaw('reloj_id, DATE(timestamp) as fecha')
            ->groupBy('reloj_id', 'fecha')
            ->orderBy('fecha')
            ->orderBy('reloj_id');

        $combinaciones = $query->get();

        if ($combinaciones->isEmpty()) {
            $this->warn('No se encontraron logs en el rango indicado.');
            return self::SUCCESS;
        }

        $this->info("  Combinaciones encontradas: {$combinaciones->count()} (empleado × día)");

        if ($dryRun) {
            $this->info('');
            $this->info('Primeras 20 combinaciones que se procesarían:');
            $this->table(['Reloj ID', 'Fecha'], $combinaciones->take(20)->map(fn($c) => [$c->reloj_id, $c->fecha])->toArray());
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Confirmas el reprocesamiento? Los registros NO marcados como "corregido manualmente" serán recalculados.')) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $bar       = $this->output->createProgressBar($combinaciones->count());
        $procesados = 0;
        $omitidos   = 0;
        $errores    = 0;

        $bar->start();

        foreach ($combinaciones as $combo) {
            try {
                $employee = Employee::with('schedules', 'schedule')
                    ->where(function ($q) use ($combo) {
                        $q->where('reloj_id', $combo->reloj_id)
                          ->orWhere('dni', $combo->reloj_id);
                    })
                    ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->first();

                if (!$employee) {
                    $omitidos++;
                    $bar->advance();
                    continue;
                }

                // Verificar si el registro existente está marcado como corregido manualmente
                $existente = AttendanceRecord::where('employee_id', $employee->id)
                    ->where('fecha', $combo->fecha)
                    ->first();

                if ($existente && $existente->corregido_manualmente) {
                    $omitidos++;
                    $bar->advance();
                    continue;
                }

                // Obtener logs del día
                $logs = AttendanceLog::where('reloj_id', $combo->reloj_id)
                    ->whereDate('timestamp', $combo->fecha)
                    ->orderBy('timestamp')
                    ->get();

                // Llamar al método privado via Reflection (para no cambiar la firma del processor)
                $reflection = new \ReflectionClass($processor);
                $method     = $reflection->getMethod('calcularYGuardar');
                $method->setAccessible(true);
                $method->invoke($processor, $employee, $combo->fecha, $logs);

                $procesados++;
            } catch (\Exception $e) {
                $errores++;
                $this->newLine();
                $this->error("Error en reloj_id={$combo->reloj_id} fecha={$combo->fecha}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  ✅ Procesados : {$procesados}");
        $this->info("  ⏭️  Omitidos  : {$omitidos} (sin empleado o corregidos manualmente)");
        if ($errores > 0) {
            $this->warn("  ❌ Errores   : {$errores}");
        }
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return self::SUCCESS;
    }
}
