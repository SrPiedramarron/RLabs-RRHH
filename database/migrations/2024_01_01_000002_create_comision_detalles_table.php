<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comision_upload_id')->constrained()->cascadeOnDelete();
            $table->string('periodo');          // "2026-03"

            // Datos del vendedor
            $table->string('vendedor');         // nombre del vendedor (limpio)

            // Datos del comprobante (del Excel de comisiones)
            $table->string('numdoc');           // "F001-00027908"
            $table->string('tipo_doc');         // "FA", "BV", "ND", "NC"
            $table->string('cod_cliente')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('condicion')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('moneda', 5)->default('S/');
            $table->decimal('tipo_cambio', 8, 4)->default(1);

            // Valores del Excel de comisiones
            $table->decimal('base_comision_venta', 14, 2)->default(0);
            $table->decimal('v_venta_contado', 14, 2)->default(0);
            $table->decimal('v_venta_credito', 14, 2)->default(0);

            // Valores del Excel de cobranzas (null = no cobrado aún)
            $table->decimal('base_comision_cobrada', 14, 2)->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('forma_pago')->nullable();
            $table->decimal('importe_cobrado', 14, 2)->nullable();

            // Resultado del cruce
            $table->enum('estado', ['cobrada', 'pendiente', 'anulada'])->default('pendiente');
            $table->string('mes_cobro')->nullable(); // "ABRIL", "MAYO" (si está en col. extra del Excel)

            // Comisión calculada (solo para cobradas)
            $table->decimal('comision_calculada', 14, 2)->default(0); // base_cobrada * 0.015
            $table->decimal('porcentaje_comision', 5, 4)->default(0.0150);

            $table->timestamps();

            $table->index(['periodo', 'vendedor']);
            $table->index(['comision_upload_id', 'estado']);
            $table->index('numdoc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_detalles');
    }
};
