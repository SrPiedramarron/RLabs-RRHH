<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_renovacion'); // 1, 2, 3...
            $table->date('fecha_fin_anterior')->nullable();
            $table->date('fecha_fin_nueva');
            $table->string('observacion', 255)->nullable();
            $table->foreignId('renovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('renovado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_renewals');
    }
};
