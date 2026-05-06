<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\RemoteCheckin;
use App\Services\FacialValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CheckinController extends Controller
{
    public function __construct(private FacialValidationService $facial) {}

    // ── Pantalla principal ────────────────────────────────────────────────────

    public function home()
    {
        $credential = Auth::guard('employee')->user();
        $employee   = $credential->employee()->with(['schedule', 'location'])->first();

        // Checkin de hoy (si existe)
        $checkinHoy = RemoteCheckin::where('employee_id', $employee->id)
            ->whereDate('fecha_hora', today())
            ->orderByDesc('fecha_hora')
            ->get();

        // Registro de asistencia de hoy
        $recordHoy = AttendanceRecord::where('employee_id', $employee->id)
            ->where('fecha', today())
            ->first();

        $tieneEntrada = $checkinHoy->where('tipo', 'entrada')->isNotEmpty()
            || ($recordHoy && $recordHoy->hora_entrada !== null);

        $tieneSalida = $checkinHoy->where('tipo', 'salida')->isNotEmpty()
            || ($recordHoy && $recordHoy->hora_salida !== null);

        return view('checkin.home', compact(
            'employee', 'checkinHoy', 'tieneEntrada', 'tieneSalida', 'recordHoy'
        ));
    }

    // ── Procesar marcación ────────────────────────────────────────────────────

    public function marcar(Request $request)
    {
        $request->validate([
            'tipo'     => ['required', 'in:entrada,salida'],
            'latitud'  => ['required', 'numeric'],
            'longitud' => ['required', 'numeric'],
            'foto'     => ['required', 'string'], // base64
        ]);

        $credential = Auth::guard('employee')->user();
        $employee   = $credential->employee;

        // ── 1. Guardar la foto del checkin ────────────────────────────────────
        $fotoPath = $this->guardarFotoCheckin($request->foto, $employee->id);

        if (! $fotoPath) {
            return back()->with('error', 'No se pudo guardar la foto. Intenta nuevamente.');
        }

        // ── 2. Crear el registro de checkin (pendiente de validación) ─────────
        $checkin = RemoteCheckin::create([
            'employee_id'      => $employee->id,
            'company_id'       => $employee->company_id,
            'tipo'             => $request->tipo,
            'fecha_hora'       => now(),
            'latitud'          => $request->latitud,
            'longitud'         => $request->longitud,
            'precision_metros' => $request->precision,
            'foto_path'        => $fotoPath,
            'estado_facial'    => 'pendiente',
            'estado_procesado' => 'pendiente',
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
        ]);

        // ── 3. Validación facial ──────────────────────────────────────────────
        if ($employee->foto_perfil) {
            $resultado = $this->facial->comparar(
                $employee->foto_perfil,
                $fotoPath,
                $employee->id
            );

            $checkin->update([
                'estado_facial'    => $resultado['estado'],
                'confianza_facial' => $resultado['confianza'],
            ]);

            // Si el rostro no coincide, marcar como rechazado
            if (! $resultado['match']) {
                $checkin->update(['estado_procesado' => 'rechazado']);

                // Si es AJAX (reintento desde JS), devolver JSON
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'  => false,
                        'facial'   => false,
                        'mensaje'  => $resultado['mensaje'],
                        'confianza' => $resultado['confianza'],
                    ], 422);
                }

                return redirect()->route('checkin.home')->with(
                    'error',
                    '❌ ' . $resultado['mensaje'] . ' Si crees que es un error, contacta a RR.HH.'
                );
            }
        } else {
            // Sin foto de perfil — se acepta pero queda marcado
            $checkin->update(['estado_facial' => 'sin_perfil']);
        }

        // ── 4. Procesar el checkin → actualizar attendance_record ─────────────
        $this->procesarCheckin($checkin, $employee);

        $emoji = $request->tipo === 'entrada' ? '🟢' : '🔴';
        $label = $request->tipo === 'entrada' ? 'Entrada' : 'Salida';

        return redirect()->route('checkin.home')->with(
            'success',
            "{$emoji} {$label} registrada correctamente a las " . now()->format('H:i')
        );
    }

    // ── Historial ─────────────────────────────────────────────────────────────

    public function historial()
    {
        $employee = Auth::guard('employee')->user()->employee;

        $checkins = RemoteCheckin::where('employee_id', $employee->id)
            ->orderByDesc('fecha_hora')
            ->paginate(20);

        return view('checkin.historial', compact('checkins', 'employee'));
    }

    // ── Mis marcaciones del mes ───────────────────────────────────────────────

    public function asistencia(Request $request)
    {
        $employee = Auth::guard('employee')->user()->employee()->with('schedule')->first();

        $mes  = $request->integer('mes',  now()->month);
        $anio = $request->integer('anio', now()->year);

        $desde = Carbon::create($anio, $mes, 1)->startOfMonth();
        $hasta = Carbon::create($anio, $mes, 1)->endOfMonth();

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')
            ->get();

        $totales = [
            'presentes'  => $records->whereIn('estado', ['presente', 'tarde'])->count(),
            'tardanzas'  => $records->where('estado', 'tarde')->count(),
            'ausentes'   => $records->where('estado', 'ausente')->count(),
            'minutos_tarde' => $records->sum('minutos_tarde'),
        ];

        // Meses disponibles para el selector (últimos 6 meses)
        $meses = collect(range(0, 5))->map(fn($i) => now()->subMonths($i));

        return view('checkin.asistencia', compact(
            'employee', 'records', 'totales', 'mes', 'anio', 'meses'
        ));
    }

    // ── PWA: manifest y service worker ───────────────────────────────────────

    public function manifest()
    {
        return response()->json([
            'name'             => 'SumaRH',
            'short_name'       => 'SumaRH',
            'description'      => 'Control de asistencia remoto',
            'start_url'        => '/checkin/home',
            'display'          => 'standalone',
            'background_color' => '#1E3A5F',
            'theme_color'      => '#2563EB',
            'icons'            => [
                ['src' => '/images/checkin/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/images/checkin/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker()
    {
        $content = "self.addEventListener('fetch', function(event) {});";
        return response($content)->header('Content-Type', 'application/javascript');
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function guardarFotoCheckin(string $base64, int $employeeId): ?string
    {
        try {
            // Limpiar header del base64 (data:image/jpeg;base64,...)
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64)[1];
            }

            $imgData  = base64_decode($base64);
            $filename = 'checkins/' . now()->format('Y/m') . "/{$employeeId}_" . now()->format('YmdHis') . '.jpg';

            Storage::disk('public')->put($filename, $imgData);
            return $filename;

        } catch (\Exception $e) {
            \Log::error("Error guardando foto checkin employee_id={$employeeId}: {$e->getMessage()}");
            return null;
        }
    }

    private function procesarCheckin(RemoteCheckin $checkin, $employee): void
    {
        $fecha = $checkin->fecha_hora->toDateString();

        $record = AttendanceRecord::firstOrCreate(
            ['employee_id' => $employee->id, 'fecha' => $fecha],
            [
                'company_id'  => $employee->company_id,
                'location_id' => $employee->location_id,
                'estado'      => 'presente',
            ]
        );

        if ($checkin->tipo === 'entrada') {
            $record->update([
                'hora_entrada'  => $checkin->fecha_hora,
                'fuente_entrada' => 'remoto',
                'estado'        => 'presente',
            ]);
        } else {
            $record->update([
                'hora_salida'  => $checkin->fecha_hora,
                'fuente_salida' => 'remoto',
            ]);
        }

        $checkin->update([
            'attendance_record_id' => $record->id,
            'estado_procesado'     => 'procesado',
        ]);
    }
}
