<?php

use App\Jobs\SyncAttendanceJob;
use App\Console\Commands\GenerateAbsenceRecords;
use App\Console\Commands\AvisarContratosPorVencer;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncAttendanceJob)->everyFifteenMinutes();

Schedule::command(GenerateAbsenceRecords::class)->dailyAt('23:30');

Schedule::command(AvisarContratosPorVencer::class)->dailyAt('08:00');