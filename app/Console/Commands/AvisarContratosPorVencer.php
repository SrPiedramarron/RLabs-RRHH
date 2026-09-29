<?php

namespace App\Console\Commands;

use App\Mail\ContratosPorVencerMail;
use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AvisarContratosPorVencer extends Command
{
    protected $signature = 'contratos:avisar-vencimiento {--dias=15 : Días de anticipación}';
    protected $description = 'Avisa por correo a cada empresa los contratos que vencen en X días (por defecto 15)';

    public function handle(): void
    {
        $dias        = (int) $this->option('dias');
        $fechaAviso  = Carbon::today()->addDays($dias)->toDateString();

        $empleados = Employee::where('active', true)
            ->whereNull('fecha_cese')
            ->whereDate('fecha_fin_contrato', $fechaAviso)
            ->with('company')
            ->get()
            ->groupBy('company_id');

        if ($empleados->isEmpty()) {
            $this->info("Ningún contrato vence el {$fechaAviso} ({$dias} días desde hoy).");
            return;
        }

        foreach ($empleados as $companyId => $trabajadores) {
            $company = Company::find($companyId);

            if (!$company || !$company->email) {
                $this->warn("Empresa {$company?->razon_social} (id {$companyId}) no tiene email configurado — se omite el aviso.");
                continue;
            }

            Mail::to($company->email)->queue(new ContratosPorVencerMail($company, $trabajadores, $dias));
            $this->info("Aviso enviado a {$company->email} ({$company->razon_social}) — {$trabajadores->count()} contrato(s).");
        }
    }
}
