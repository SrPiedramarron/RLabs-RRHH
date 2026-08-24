<?php
\Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');

\Illuminate\Support\Facades\DB::table('comision_detalles')->truncate();
\Illuminate\Support\Facades\DB::table('comision_uploads')->truncate();
\Illuminate\Support\Facades\DB::table('attendance_records')->truncate();
\Illuminate\Support\Facades\DB::table('planilla_liquidaciones')->truncate();
\Illuminate\Support\Facades\DB::table('employees')->truncate();
\Illuminate\Support\Facades\DB::table('schedules')->truncate();
\Illuminate\Support\Facades\DB::table('departments')->truncate();
\Illuminate\Support\Facades\DB::table('locations')->truncate();

\Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Limpio." . PHP_EOL;
