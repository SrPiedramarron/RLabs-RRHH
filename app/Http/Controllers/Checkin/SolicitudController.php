<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Services\VacacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        $inicial = app(\App\Services\SolicitudJefeService::class)->estadoInicial($employee, 'vacaciones');

        Solicitud::create([
            'employee_id'  => $employee->id,
            'company_id'   => $employee->company_id,
            'tipo'         => 'vacaciones',
            'estado'       => $inicial['estado'],
            'jefe_id'      => $inicial['jefe_id'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin'    => $data['fecha_fin'],
            'motivo'       => $data['motivo'] ?? null,
        ]);

        return redirect()->route('checkin.solicitudes.index')
            ->with('success', '✅ Tu solicitud de vacaciones fue enviada. ' . ($inicial['jefe_id'] ? 'Primero la revisa tu jefe y luego RRHH.' : 'RRHH la revisará pronto.'));
    }

    // ── Permiso (tardanza/ausencia justificada por un motivo puntual) ───────────

    public function createPermiso()
    {
        $employee = Auth::guard('employee')->user()->employee;

        return view('checkin.solicitudes.create-permiso', compact('employee'));
    }

    public function storePermiso(Request $request)
    {
        $employee = Auth::guard('employee')->user()->employee;

        $data = $request->validate([
            'categoria'    => ['required', 'in:medico,descanso,personal,tramite,otro'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo'       => ['required', 'string', 'max:500'],
            'adjunto'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $categorias = [
            'medico'   => 'Cita/examen médico',
            'descanso' => 'Descanso médico',
            'personal' => 'Motivo personal',
            'tramite'  => 'Trámite/diligencia',
            'otro'     => 'Otro',
        ];

        $adjuntoPath = null;
        if ($request->hasFile('adjunto')) {
            $adjuntoPath = $request->file('adjunto')->store("solicitudes/{$employee->id}", 'public');
        }

        $inicial = app(\App\Services\SolicitudJefeService::class)->estadoInicial($employee, 'permiso');

        Solicitud::create([
            'employee_id'  => $employee->id,
            'company_id'   => $employee->company_id,
            'tipo'         => 'permiso',
            'estado'       => $inicial['estado'],
            'jefe_id'      => $inicial['jefe_id'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin'    => $data['fecha_fin'],
            'motivo'       => '[' . $categorias[$data['categoria']] . '] ' . $data['motivo'],
            'adjunto_path' => $adjuntoPath,
        ]);

        return redirect()->route('checkin.solicitudes.index')
            ->with('success', '✅ Tu solicitud de permiso fue enviada. ' . ($inicial['jefe_id'] ? 'Primero la revisa tu jefe y luego RRHH.' : 'RRHH la revisará pronto.'));
    }

    // ── Corrección de horas (marcación olvidada o incorrecta) ───────────────────

    public function createCorreccion()
    {
        $employee = Auth::guard('employee')->user()->employee;

        return view('checkin.solicitudes.create-correccion', compact('employee'));
    }

    public function storeCorreccion(Request $request)
    {
        $employee = Auth::guard('employee')->user()->employee;

        $data = $request->validate([
            'fecha_registro'          => ['required', 'date', 'before_or_equal:today'],
            'hora_entrada_solicitada' => ['nullable', 'date_format:H:i'],
            'hora_salida_solicitada'  => ['nullable', 'date_format:H:i'],
            'motivo'                  => ['required', 'string', 'max:500'],
            'adjunto'                 => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if (empty($data['hora_entrada_solicitada']) && empty($data['hora_salida_solicitada'])) {
            return back()->withErrors(['hora_entrada_solicitada' => 'Ingresa al menos la hora de entrada o de salida correcta.'])->withInput();
        }

        $adjuntoPath = $request->file('adjunto')->store("solicitudes/{$employee->id}", 'public');

        Solicitud::create([
            'employee_id'             => $employee->id,
            'company_id'              => $employee->company_id,
            'tipo'                    => 'correccion_horas',
            'estado'                  => 'pendiente',
            'fecha_registro'          => $data['fecha_registro'],
            'hora_entrada_solicitada' => $data['hora_entrada_solicitada'] ?? null,
            'hora_salida_solicitada'  => $data['hora_salida_solicitada'] ?? null,
            'motivo'                  => $data['motivo'],
            'adjunto_path'            => $adjuntoPath,
        ]);

        return redirect()->route('checkin.solicitudes.index')
            ->with('success', '✅ Tu solicitud de corrección fue enviada. RRHH la revisará pronto.');
    }
}
