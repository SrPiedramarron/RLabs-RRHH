<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE remote_checkins MODIFY tipo ENUM('entrada','salida','salida_refrigerio','regreso_refrigerio') NOT NULL");
        DB::statement("ALTER TABLE remote_checkins MODIFY estado_facial ENUM('pendiente','aprobado','rechazado','sin_perfil','error','no_aplica') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM remote_checkins WHERE tipo IN ('salida_refrigerio','regreso_refrigerio')");
        DB::statement("UPDATE remote_checkins SET estado_facial = 'pendiente' WHERE estado_facial = 'no_aplica'");
        DB::statement("ALTER TABLE remote_checkins MODIFY tipo ENUM('entrada','salida') NOT NULL");
        DB::statement("ALTER TABLE remote_checkins MODIFY estado_facial ENUM('pendiente','aprobado','rechazado','sin_perfil','error') NOT NULL DEFAULT 'pendiente'");
    }
};
