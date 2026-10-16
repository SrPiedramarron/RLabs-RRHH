<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 40);                 // ver App\Support\Recordatorios
            $table->boolean('activo')->default(true);
            $table->text('destinatarios');              // correos separados por ; , o salto de línea
            $table->string('modo', 20);                 // dia_mes | dias_antes | diario
            $table->unsignedTinyInteger('dia_mes')->nullable();     // modo dia_mes (1-31; 31 = último día)
            $table->unsignedSmallInteger('dias_antes')->nullable(); // modo dias_antes
            $table->unsignedSmallInteger('ventana_dias')->nullable(); // días hacia adelante que cubre el aviso (dia_mes / diario)
            $table->string('hora', 5)->default('08:00');
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
        });

        $ahora = now();

        foreach (DB::table('companies')->get() as $c) {
            $esInProcess = stripos((string) $c->razon_social, 'INDUSTRIAL PROCESS') !== false;

            if ($esInProcess) {
                // Pedido de RRHH (oct. 2026): contratos el 1.º de cada mes y cumpleaños 24 h antes.
                DB::table('recordatorios')->insert([
                    [
                        'company_id' => $c->id, 'tipo' => 'contratos_por_vencer', 'activo' => true,
                        'destinatarios' => 'rrhh@inprocess.com.pe; gmartinez@inprocess.com.pe',
                        'modo' => 'dia_mes', 'dia_mes' => 1, 'ventana_dias' => 30, 'hora' => '08:00',
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ],
                    [
                        'company_id' => $c->id, 'tipo' => 'cumpleanos', 'activo' => true,
                        'destinatarios' => 'rrhh@inprocess.com.pe; marketing@inprocess.com.pe',
                        'modo' => 'dias_antes', 'dias_antes' => 1, 'hora' => '08:00',
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ],
                ]);
            } elseif ($c->email) {
                // Lo que ya hacía el sistema: aviso diario de contratos que vencen en 15 días.
                DB::table('recordatorios')->insert([
                    'company_id' => $c->id, 'tipo' => 'contratos_por_vencer', 'activo' => true,
                    'destinatarios' => trim($c->email . '; ' . ($c->email_cc ?? ''), '; '),
                    'modo' => 'dias_antes', 'dias_antes' => 15, 'hora' => '08:00',
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
