<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 'planilla' (default) o 'locador': locadores de servicios que emiten
            // recibo por honorarios; no entran a planilla ni a asistencia.
            $table->string('tipo_vinculo', 20)->default('planilla')->after('active');
            $table->decimal('honorario_mensual', 10, 2)->nullable()->after('tipo_vinculo');
            $table->boolean('suspension_4ta')->default(false)->after('honorario_mensual');
            $table->string('ruc_locador', 11)->nullable()->after('suspension_4ta');
        });

        Schema::create('honorarios_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('periodo', 7);            // YYYY-MM
            $table->unsignedTinyInteger('quincena');  // 1 o 2
            $table->decimal('honorario_bruto', 10, 2);
            $table->decimal('retencion_4ta', 10, 2)->default(0);
            $table->decimal('neto_pagar', 10, 2);
            $table->boolean('suspension_4ta')->default(false); // vigente al calcular
            $table->string('recibo_numero', 30)->nullable();
            $table->date('recibo_fecha')->nullable();
            $table->boolean('pagado')->default(false);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'periodo', 'quincena']);
        });

        foreach ([
            ['tasa_retencion_4ta', 'Tasa de retención de 4.ª categoría sobre recibos por honorarios (0.08 = 8%)', 0.08],
            ['umbral_retencion_4ta', 'Monto desde el cual el recibo por honorarios lleva retención de 4.ª categoría (S/)', 1500],
        ] as [$clave, $desc, $valor]) {
            DB::table('parametros_legales')->insert([
                'clave' => $clave, 'descripcion' => $desc, 'valor' => $valor,
                'vigente_desde' => '2026-01-01', 'vigente_hasta' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parametros_legales')->whereIn('clave', ['tasa_retencion_4ta', 'umbral_retencion_4ta'])->delete();
        Schema::dropIfExists('honorarios_pagos');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tipo_vinculo', 'honorario_mensual', 'suspension_4ta', 'ruc_locador']);
        });
    }
};
