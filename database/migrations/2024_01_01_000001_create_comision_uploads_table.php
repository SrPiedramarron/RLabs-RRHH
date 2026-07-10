<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('periodo');          // e.g. "2026-03"
            $table->string('mes_nombre');       // e.g. "MARZO 2026"
            $table->string('archivo_cobranzas');
            $table->string('archivo_comisiones');
            $table->enum('estado', ['procesando', 'completado', 'error'])->default('procesando');
            $table->text('error_mensaje')->nullable();
            $table->unsignedInteger('total_facturas')->default(0);
            $table->unsignedInteger('total_cobradas')->default(0);
            $table->unsignedInteger('total_pendientes')->default(0);
            $table->unsignedInteger('total_anuladas')->default(0);
            $table->decimal('total_base_cobrada', 14, 2)->default(0);
            $table->decimal('total_comision', 14, 2)->default(0);
            $table->foreignId('procesado_por')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_uploads');
    }
};
