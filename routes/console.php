<?php

use App\Jobs\SyncAttendanceJob;
use App\Console\Commands\GenerateAbsenceRecords;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncAttendanceJob)->everyFifteenMinutes();

Schedule::command(GenerateAbsenceRecords::class)->dailyAt('23:30');