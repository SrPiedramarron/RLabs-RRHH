<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('direccion', 300)->nullable();
            $table->string('ubigeo', 6)->nullable();
            // Configuración del reloj ZKTeco
            $table->string('reloj_ip', 15)->nullable();
            $table->integer('reloj_puerto')->default(4370);
            $table->string('reloj_modelo', 50)->nullable();
            $table->boolean('reloj_activo')->default(true);
            $table->timestamp('ultima_sync')->nullable();
            $table->enum('sync_estado', ['ok', 'error', 'pendiente'])->default('pendiente');
            $table->text('sync_error_msg')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
