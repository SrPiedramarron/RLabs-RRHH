<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use App\Models\RemoteCheckin;
use App\Services\AttendanceProcessor;
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

        // Refrigerio: solo si el horario del trabajador lo contempla. Se
        // decide solo con las marcaciones de la app (no con el registro de
        // asistencia, que puede traer el refrigerio inferido del horario).
        $usaRefrigerio = (bool) $employee->schedule?->refrigerio_inicio;
        $tieneSalidaRefrigerio  = $checkinHoy->where('tipo', 'salida_refrigerio')->isNotEmpty();
        $tieneRegresoRefrigerio = $checkinHoy->where('tipo', 'regreso_refrigerio')->isNotEmpty();

        return view('checkin.home', compact(
            'employee', 'checkinHoy', 'tieneEntrada', 'tieneSalida', 'recordHoy',
            'usaRefrigerio', 'tieneSalidaRefrigerio', 'tieneRegresoRefrigerio'
        ));
    }

    // ── Procesar marcación ────────────────────────────────────────────────────

    public function marcar(Request $request)
    {
        $request->validate(['tipo' => ['required', 'in:entrada,salida,salida_refrigerio,regreso_refrigerio']]);

        $credential = Auth::guard('employee')->user();
        $employee   = $credential->employee;

        // Salida/regreso de refrigerio: sin foto ni validación facial (igual
        // que en el reloj, pedido de RRHH oct. 2026). El GPS es opcional.
        if (in_array($request->tipo, RemoteCheckin::TIPOS_REFRIGERIO, true)) {
            return $this->marcarRefrigerio($request, $employee);
        }

        $request->validate([
            'latitud'  => ['required', 'numeric'],
            'longitud' => ['required', 'numeric'],
            'foto'     => ['required', 'string'], // base64
        ]);

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

    // ── Refrigerio (sin foto) ─────────────────────────────────────────────────

    private function marcarRefrigerio(Request $request, $employee)
    {
        $request->validate([
            'latitud'  => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
        ]);

        $hoy    = RemoteCheckin::where('employee_id', $employee->id)->whereDate('fecha_hora', today())->get();
        $record = AttendanceRecord::where('employee_id', $employee->id)->where('fecha', today())->first();

        $tieneEntrada = $hoy->where('tipo', 'entrada')->isNotEmpty() || ($record && $record->hora_entrada !== null);
        $tieneSalida  = $hoy->where('tipo', 'salida')->isNotEmpty() || ($record && $record->hora_salida !== null);
        $yaSalioRef   = $hoy->where('tipo', 'salida_refrigerio')->isNotEmpty();
        $yaRegreso    = $hoy->where('tipo', 'regreso_refrigerio')->isNotEmpty();

        $error = match (true) {
            ! $tieneEntrada => 'Primero registra tu entrada.',
            $tieneSalida    => 'Ya registraste tu salida de hoy.',
            $request->tipo === 'salida_refrigerio' && $yaSalioRef  => 'Ya registraste tu salida a refrigerio.',
            $request->tipo === 'regreso_refrigerio' && ! $yaSalioRef => 'Primero registra tu salida a refrigerio.',
            $request->tipo === 'regreso_refrigerio' && $yaRegreso  => 'Ya registraste tu regreso de refrigerio.',
            default         => null,
        };

        if ($error) {
            return redirect()->route('checkin.home')->with('error', $error);
        }

        $checkin = RemoteCheckin::create([
            'employee_id'      => $employee->id,
            'company_id'       => $employee->company_id,
            'tipo'             => $request->tipo,
            'fecha_hora'       => now(),
            'latitud'          => $request->latitud,
            'longitud'         => $request->longitud,
            'precision_metros' => $request->precision,
            'foto_path'        => null,
            'estado_facial'    => 'no_aplica',
            'estado_procesado' => 'pendiente',
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
        ]);

        $this->procesarCheckin($checkin, $employee);

        $label = $request->tipo === 'salida_refrigerio' ? '🍽️ Salida a refrigerio' : '🍽️ Regreso de refrigerio';

        return redirect()->route('checkin.home')->with(
            'success',
            "{$label} registrado a las " . now()->format('H:i')
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
            'name'             => 'RLabs RRHH',
            'short_name'       => 'RLabs RRHH',
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
        $content = <<<'JS'
self.addEventListener('fetch', function(event) {});

self.addEventListener('push', function (event) {
    let data = { title: 'RLabs RRHH', body: 'Tienes una notificación nueva.', url: '/checkin/solicitudes' };
    try { data = event.data.json(); } catch (e) {}

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/images/checkin/icon-192.png',
            badge: '/images/checkin/icon-192.png',
            data: { url: data.url || '/checkin/solicitudes' },
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = event.notification.data?.url || '/checkin/solicitudes';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (const client of clientList) {
                if (client.url.includes(url) && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
JS;

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

    /**
     * La app es solo otro medio de marcación: cada marcación aprobada se
     * guarda como una marcación más (igual que las del reloj biométrico) y
     * el día se recalcula con el MISMO procesador — así tardanza, horas,
     * horas extra y refrigerio real se calculan idéntico al reloj (pedido
     * de RRHH, oct. 2026). Tipos de marcación: 0 entrada, 1 salida,
     * 4 salida a refrigerio, 5 regreso de refrigerio.
     */
    /**
     * La app es solo otro medio de marcación: cada marcación aprobada se
     * guarda como una marcación más (igual que las del reloj biométrico) y
     * el día se recalcula con el MISMO procesador — así tardanza, horas,
     * horas extra y refrigerio real se calculan idéntico al reloj (pedido
     * de RRHH, oct. 2026). Tipos de marcación: 0 entrada, 1 salida,
     * 4 salida a refrigerio, 5 regreso de refrigerio.
     */
    private function procesarCheckin(RemoteCheckin $checkin, $employee): void
    {
        $fecha    = $checkin->fecha_hora->toDateString();
        $relojId  = (int) ($employee->reloj_id ?: $employee->dni);
        $tipoLog  = ['entrada' => 0, 'salida' => 1, 'salida_refrigerio' => 4, 'regreso_refrigerio' => 5][$checkin->tipo];

        if ($employee->location_id) {
            AttendanceLog::create([
                'location_id' => $employee->location_id,
                'reloj_uid'   => 0,
                'reloj_id'    => $relojId,
                'timestamp'   => $checkin->fecha_hora,
                'tipo'        => $tipoLog,
                'estado'      => 0,
                'raw_data'    => [
                    'fuente'            => 'app',
                    'remote_checkin_id' => $checkin->id,
                    'latitud'           => $checkin->latitud,
                    'longitud'          => $checkin->longitud,
                ],
                'procesado'   => true,
                'created_at'  => now(),
            ]);

            app(AttendanceProcessor::class)->procesarDia($employee, $fecha, $relojId);
        }

        $record = AttendanceRecord::firstOrCreate(
            ['employee_id' => $employee->id, 'fecha' => $fecha],
            [
                'company_id'  => $employee->company_id,
                'location_id' => $employee->location_id,
                'estado'      => 'presente',
            ]
        );

        // Respaldo (trabajador sin sede: no se puede crear marcación): se
        // guarda directo la hora, como antes.
        if (! $employee->location_id) {
            match ($checkin->tipo) {
                'entrada'            => $record->update(['hora_entrada' => $checkin->fecha_hora]),
                'salida'             => $record->update(['hora_salida' => $checkin->fecha_hora]),
                'salida_refrigerio'  => $record->update(['inicio_refrigerio' => $checkin->fecha_hora]),
                'regreso_refrigerio' => $record->update(['fin_refrigerio' => $checkin->fecha_hora]),
            };
            $record->refresh();
        } else {
            $record->refresh();
        }

        // Fuente "App móvil" solo si esta marcación ES la hora que quedó en
        // el registro (si el reloj marcó antes/después, manda el reloj).
        if ($checkin->tipo === 'entrada' && $record->hora_entrada && $record->hora_entrada->equalTo($checkin->fecha_hora)) {
            $record->update(['fuente_entrada' => 'remoto']);
        }
        if ($checkin->tipo === 'salida' && $record->hora_salida && $record->hora_salida->equalTo($checkin->fecha_hora)) {
            $record->update(['fuente_salida' => 'remoto']);
        }

        $checkin->update([
            'attendance_record_id' => $record->id,
            'estado_procesado'     => 'procesado',
        ]);
    }
}
