<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cierres_periodo')) {
            return;
        }

        Schema::create('cierres_periodo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('periodo', 7); // 2026-10
            $table->boolean('cerrado')->default(true);
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('reabierto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reabierto_at')->nullable();
            $table->text('motivo_reapertura')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_periodo');
    }
};
