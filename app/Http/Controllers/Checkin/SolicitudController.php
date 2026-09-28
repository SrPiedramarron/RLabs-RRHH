<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Services\VacacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolicitudController extends Controller
{
    public function index()
    {
        $employee = Auth::guard('employee')->user()->employee;

        $solicitudes = Solicitud::where('employee_id', $employee->id)
            ->latest()
            ->paginate(15);

        $saldo = app(VacacionesService::class)->calcularSaldo($employee);

        return view('checkin.solicitudes.index', compact('employee', 'solicitudes', 'saldo'));
    }

    public function create()
    {
        $employee = Auth::guard('employee')->user()->employee;
        $saldo    = app(VacacionesService::class)->calcularSaldo($employee);

        return view('checkin.solicitudes.create', compact('employee', 'saldo'));
    }

    public function store(Request $request)
    {
        $employee = Auth::guard('employee')->user()->employee;

        $data = $request->validate([
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo'       => ['nullable', 'string', 'max:500'],
        ]);

        Solicitud::create([
            'employee_id'  => $employee->id,
            'company_id'   => $employee->company_id,
            'tipo'         => 'vacaciones',
            'estado'       => 'pendiente',
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin'    => $data['fecha_fin'],
            'motivo'       => $data['motivo'] ?? null,
        ]);

        return redirect()->route('checkin.solicitudes.index')
            ->with('success', '✅ Tu solicitud de vacaciones fue enviada. RRHH la revisará pronto.');
    }
}
