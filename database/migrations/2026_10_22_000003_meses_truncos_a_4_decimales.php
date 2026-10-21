<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// RRHH (oct. 2026): los meses de la liquidación se redondean a 4 decimales (5.4667 y no 5.47),
// porque con 2 decimales el total se desviaba hasta S/ 2 de su cálculo.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_gratificacion_trunca DECIMAL(7,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_cts_trunca DECIMAL(7,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_vacaciones_truncas DECIMAL(7,4) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_gratificacion_trunca DECIMAL(5,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_cts_trunca DECIMAL(5,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE liquidaciones_cese MODIFY meses_vacaciones_truncas DECIMAL(5,2) NULL');
    }
};
