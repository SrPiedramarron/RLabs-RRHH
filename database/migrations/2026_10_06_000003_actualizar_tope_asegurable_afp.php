<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Remuneración máxima asegurable vigente (SBS): S/ 12,672.65, desde 01/09/2026.
    // Se cierra la tasa anterior y se abre una nueva copiando comisión/prima/aporte.
    public function up(): void
    {
        $desde = '2026-09-01';
        $abiertas = DB::table('afp_tasas')->whereNull('vigente_hasta')->where('vigente_desde', '<', $desde)->get();

        foreach ($abiertas as $t) {
            $nueva = (array) $t;
            unset($nueva['id']);
            DB::table('afp_tasas')->where('id', $t->id)->update(['vigente_hasta' => '2026-08-31']);
            $nueva['tope_remuneracion_asegurable'] = 12672.65;
            $nueva['vigente_desde'] = $desde;
            $nueva['vigente_hasta'] = null;
            $nueva['created_at'] = $nueva['updated_at'] = now();
            DB::table('afp_tasas')->insert($nueva);
        }
    }

    public function down(): void
    {
        DB::table('afp_tasas')->where('vigente_desde', '2026-09-01')->where('tope_remuneracion_asegurable', 12672.65)->delete();
        DB::table('afp_tasas')->where('vigente_hasta', '2026-08-31')->update(['vigente_hasta' => null]);
    }
};
