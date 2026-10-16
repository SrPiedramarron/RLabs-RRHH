<?php

use App\Jobs\SyncAttendanceJob;
use App\Console\Commands\GenerateAbsenceRecords;
use App\Console\Commands\AvisarContratosPorVencer;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncAttendanceJob)->everyFifteenMinutes();

Schedule::command(GenerateAbsenceRecords::class)->dailyAt('23:30');

// Recordatorios por correo configurables (Administración > Recordatorios): contratos, cumpleaños, etc.
Schedule::command(\App\Console\Commands\EnviarRecordatorios::class)->everyTenMinutes();

// Vacaciones/permisos sin respuesta del jefe directo pasan a RRHH tras el plazo configurado.
Schedule::command(\App\Console\Commands\EscalarSolicitudesJefe::class)->everyTenMinutes();
