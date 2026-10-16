<?php

namespace App\Console\Commands;

use App\Models\Recordatorio;
use App\Services\RecordatorioService;
use Illuminate\Console\Command;

class EnviarRecordatorios extends Command
{
    protected $signature = 'recordatorios:enviar {--forzar : Envía los activos aunque no sea su día u hora}';
    protected $description = 'Envía por correo los recordatorios configurados que toca enviar ahora';

    public function handle(RecordatorioService $servicio): int
    {
        $enviados = 0;

        foreach (Recordatorio::with('company')->where('activo', true)->get() as $r) {
            if (! $this->option('forzar') && ! $servicio->debeEnviarse($r)) {
                continue;
            }

            try {
                $n = $servicio->enviar($r);
                $r->update(['ultimo_envio_at' => now()]);

                $this->info("{$r->tipo_label} ({$r->company?->razon_social}): " . ($n ? "enviado con {$n} elemento(s)" : 'nada que avisar hoy'));
                $enviados += $n ? 1 : 0;
            } catch (\Throwable $e) {
                // Un correo fallido no impide los demás; se reintenta en la próxima pasada (cada 10 min).
                $this->error("{$r->tipo_label} ({$r->company?->razon_social}): {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
