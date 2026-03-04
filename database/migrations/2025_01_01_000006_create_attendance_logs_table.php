<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->integer('reloj_uid');
            $table->integer('reloj_id');
            $table->dateTime('timestamp');
            $table->tinyInteger('tipo')->default(0);   // 0=entrada, 1=salida
            $table->tinyInteger('estado')->default(0);
            $table->json('raw_data')->nullable();
            $table->boolean('procesado')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reloj_id', 'timestamp']);
            $table->index('procesado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
