<?php

use App\Jobs\SyncAttendanceJob;
use App\Console\Commands\GenerateAbsenceRecords;
use App\Console\Commands\AvisarContratosPorVencer;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncAttendanceJob)->everyFifteenMinutes();

Schedule::command(GenerateAbsenceRecords::class)->dailyAt('23:30');

// Recordatorios por correo configurables (Administración > Recordatorios): contratos, cumpleaños, etc.
Schedule::command(\App\Console\Commands\EnviarRecordatorios::class)->everyTenMinutes();

// Revisión semanal de cruces de datos entre empresas: avisa por correo solo si hay errores.
Schedule::command('rrhh:auditar-cruces-empresa --notificar --silencioso')->weeklyOn(1, '07:00');

// Vacaciones/permisos sin respuesta del jefe directo pasan a RRHH tras el plazo configurado.
Schedule::command(\App\Console\Commands\EscalarSolicitudesJefe::class)->everyTenMinutes();
