<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'otras_retenciones_5ta')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('otras_retenciones_5ta', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('otras_retenciones_5ta'));
    }
};
