<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comision_uploads', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Backfill: todas las cargas existentes hasta hoy son de InProcess
        // (confirmado con Ricardo, ago 2026).
        $inProcessId = DB::table('companies')
            ->where('razon_social', 'like', '%INDUSTRIAL PROCESS%')
            ->orWhere('ruc', '20514706302')
            ->value('id');

        if ($inProcessId) {
            DB::table('comision_uploads')->whereNull('company_id')->update(['company_id' => $inProcessId]);
        }
    }

    public function down(): void
    {
        Schema::table('comision_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
