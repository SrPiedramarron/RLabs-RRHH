<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamp('iniciado_en');
            $table->timestamp('finalizado_en')->nullable();
            $table->enum('estado', ['ejecutando', 'completado', 'error'])->default('ejecutando');
            $table->integer('registros_leidos')->default(0);
            $table->integer('registros_nuevos')->default(0);
            $table->text('error_mensaje')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
