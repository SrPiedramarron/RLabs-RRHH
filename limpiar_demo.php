<?php
// Borra en orden correcto por las foreign keys
\Illuminate\Support\Facades\DB::table('comision_detalles')->truncate();
\Illuminate\Support\Facades\DB::table('comision_uploads')->truncate();
\Illuminate\Support\Facades\DB::table('attendance_records')->truncate();
\Illuminate\Support\Facades\DB::table('employees')->truncate();
\Illuminate\Support\Facades\DB::table('schedules')->truncate();
\Illuminate\Support\Facades\DB::table('departments')->truncate();
\Illuminate\Support\Facades\DB::table('locations')->truncate();
echo "Limpio." . PHP_EOL;
