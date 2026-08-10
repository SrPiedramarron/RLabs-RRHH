<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_devices', function (Blueprint $table) {
            $table->foreignId('employee_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->after('employee_id')->constrained();
            $table->string('reloj_uid')->after('location_id');
            $table->unsignedInteger('reloj_id')->nullable()->after('reloj_uid');
            $table->boolean('active')->default(true)->after('reloj_id');

            $table->unique(['location_id', 'reloj_uid']);
            $table->index(['employee_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_devices', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['location_id']);
            $table->dropUnique(['location_id', 'reloj_uid']);
            $table->dropColumn(['employee_id', 'location_id', 'reloj_uid', 'reloj_id', 'active']);
        });
    }
};
