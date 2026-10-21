<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'otras_rentas_afectas')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('otras_rentas_afectas', 12, 2)->default(0);
                $table->unsignedSmallInteger('otras_rentas_anio')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['otras_rentas_afectas', 'otras_rentas_anio']));
    }
};
