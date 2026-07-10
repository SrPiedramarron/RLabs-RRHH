<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('comision_detalles', function (Blueprint $table) {
        $table->enum('estado', ['cobrada', 'pendiente', 'anulada', 'huerfana'])
              ->default('pendiente')
              ->change();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
