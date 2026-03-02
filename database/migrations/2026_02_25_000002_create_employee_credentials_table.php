<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales de acceso a la PWA de marcado remoto.
     * Separadas de la tabla users (que es para el panel Filament/admin).
     */
    public function up(): void
    {
        Schema::create('employee_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('password');                          // bcrypt
            $table->boolean('active')->default(true);
            $table->timestamp('ultimo_acceso')->nullable();
            $table->string('token_dispositivo')->nullable()      // para push notifications futuras
                ->comment('Token FCM del dispositivo móvil');
            $table->timestamps();

            $table->unique('employee_id');                       // 1 credencial por empleado
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_credentials');
    }
};
