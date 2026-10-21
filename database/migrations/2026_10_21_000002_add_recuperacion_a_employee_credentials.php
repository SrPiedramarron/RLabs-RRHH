<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_credentials', 'reset_code')) {
            Schema::table('employee_credentials', function (Blueprint $table) {
                $table->string('reset_code')->nullable();            // hash del código de 6 dígitos
                $table->timestamp('reset_expires_at')->nullable();
                $table->unsignedTinyInteger('reset_intentos')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('employee_credentials', fn (Blueprint $t) => $t->dropColumn(['reset_code', 'reset_expires_at', 'reset_intentos']));
    }
};
