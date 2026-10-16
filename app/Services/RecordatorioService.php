<?php

namespace App\Services;

use App\Mail\RecordatorioMail;
use App\Models\Recordatorio;
use App\Support\Recordatorios;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class RecordatorioService
{
    /** ¿Toca enviarlo ahora? Respeta día, hora y que no se haya enviado ya hoy. */
    public function debeEnviarse(Recordatorio $r, ?Carbon $ahora = null): bool
    {
        $ahora = $ahora ?? now();

        if (! $r->activo || ! $r->correos()) {
            return false;
        }

        $programado = Carbon::parse($ahora->toDateString() . ' ' . ($r->hora ?: '08:00'));
        if ($ahora->lt($programado)) {
            return false;
        }

        if ($r->ultimo_envio_at && $r->ultimo_envio_at->gte($programado)) {
            return false; // ya se envió hoy
        }

        if ($r->modo === 'dia_mes') {
            $objetivo = min((int) $r->dia_mes, $ahora->daysInMonth);

            return $ahora->day === $objetivo;
        }

        return true;
    }

    /**
     * Arma y envía el aviso. Si no hay nada que avisar, no envía (salvo prueba).
     *
     * @return int cantidad de elementos avisados (0 si no se envió)
     */
    public function enviar(Recordatorio $r, bool $prueba = false, ?Carbon $hoy = null): int
    {
        $datos = Recordatorios::recolectar($r, $hoy);
        $n     = count($datos['filas']);

        if ($n === 0 && ! $prueba) {
            return 0;
        }

        Mail::to($r->correos())->send(new RecordatorioMail(
            titulo: ($prueba ? '[PRUEBA] ' : '') . $datos['titulo'],
            intro: $n === 0 ? $datos['intro'] . ' (Hoy no hay elementos que avisar.)' : $datos['intro'],
            columnas: $datos['columnas'],
            filas: $datos['filas'],
            empresa: (string) $r->company?->razon_social,
            cuando: $r->cuando,
        ));

        return $n;
    }
}
