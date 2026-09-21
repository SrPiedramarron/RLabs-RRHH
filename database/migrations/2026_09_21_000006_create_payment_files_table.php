<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('banco', 20); // 'BBVA', 'BCP', ...
            $table->string('archivo_path', 255);
            $table->unsignedSmallInteger('cantidad_registros');
            $table->decimal('monto_total', 10, 2);
            $table->foreignId('generado_por')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_files');
    }
};
