<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Los locadores (recibo por honorarios) no tienen horario: schedule_id pasa a ser opcional.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employees MODIFY schedule_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // No se revierte: volver a NOT NULL fallaría si ya hay locadores sin horario.
    }
};
