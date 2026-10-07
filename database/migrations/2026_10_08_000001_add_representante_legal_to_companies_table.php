<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Quien firma por la empresa en constancias (CTS, etc.).
            $table->string('representante_legal', 150)->nullable()->after('email_cc');
            $table->string('representante_legal_cargo', 100)->nullable()->after('representante_legal');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['representante_legal', 'representante_legal_cargo']);
        });
    }
};
