<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Services\SolicitudJefeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Solicitudes de vacaciones y permisos del equipo, para el jefe directo. */
class EquipoController extends Controller
{
    public function index()
    {
        $jefe = Auth::guard('employee')->user()->employee;

        $pendientes = Solicitud::with('employee')
            ->where('jefe_id', $jefe->id)
            ->where('estado', 'pendiente_jefe')
            ->orderBy('created_at')
            ->get();

        $resueltas = Solicitud::with('employee')
            ->where('jefe_id', $jefe->id)
            ->whereNotNull('jefe_resuelto_at')
            ->latest('jefe_resuelto_at')
            ->limit(15)
            ->get();

        $horasEspera = app(SolicitudJefeService::class)->horasEspera();

        return view('checkin.equipo', compact('jefe', 'pendientes', 'resueltas', 'horasEspera'));
    }

    public function aprobar(Request $request, Solicitud $solicitud, SolicitudJefeService $servicio)
    {
        $this->autorizar($solicitud);

        $data = $request->validate(['comentario' => ['nullable', 'string', 'max:500']]);
        $servicio->aprobar($solicitud, $data['comentario'] ?? null);

        return redirect()->route('checkin.equipo')->with('success', '✅ Aprobaste la solicitud. Pasó a RRHH.');
    }

    public function rechazar(Request $request, Solicitud $solicitud, SolicitudJefeService $servicio)
    {
        $this->autorizar($solicitud);

        $data = $request->validate(['comentario' => ['required', 'string', 'max:500']]);
        $servicio->rechazar($solicitud, $data['comentario']);

        return redirect()->route('checkin.equipo')->with('success', 'Rechazaste la solicitud.');
    }

    private function autorizar(Solicitud $solicitud): void
    {
        $jefe = Auth::guard('employee')->user()->employee;

        abort_unless($solicitud->jefe_id === $jefe->id && $solicitud->estado === 'pendiente_jefe', 403);
    }
}
