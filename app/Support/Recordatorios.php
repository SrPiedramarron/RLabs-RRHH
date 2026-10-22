<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Recordatorio;
use App\Models\Solicitud;
use Carbon\Carbon;

/**
 * Tipos de recordatorio por correo y la consulta de lo que cada uno avisa.
 * Para agregar un tipo nuevo: sumarlo a TIPOS y escribir su método recolectar*.
 */
class Recordatorios
{
    public const TIPOS = [
        'contratos_por_vencer' => [
            'label' => 'Renovación de contratos',
            'ayuda' => 'Contratos que vencen: los del periodo que cubra el aviso.',
            'ventana_defecto' => 30,
        ],
        'cumpleanos' => [
            'label' => 'Cumpleaños',
            'ayuda' => 'Trabajadores que cumplen años en la fecha que cubra el aviso.',
            'ventana_defecto' => 0,
        ],
        'aniversarios' => [
            'label' => 'Aniversario laboral',
            'ayuda' => 'Trabajadores que cumplen un año más de servicio.',
            'ventana_defecto' => 0,
        ],
        'ceses_sin_liquidar' => [
            'label' => 'Ceses sin liquidar',
            'ayuda' => 'Ceses de los últimos 60 días que aún no tienen liquidación calculada.',
            'ventana_defecto' => 0,
        ],
        'cruces_empresas' => [
            'label' => 'Cruces de datos entre empresas',
            'ayuda' => 'Revisión que avisa solo si hay datos cruzados entre empresas (áreas, sedes, horarios o registros de otra empresa). No depende de una empresa.',
            'ventana_defecto' => 0,
        ],
        'horas_sobre_tope' => [
            'label' => 'Horas ordinarias sobre el tope del horario',
            'ayuda' => 'Revisión que avisa solo si hay días donde las horas ordinarias (las de SUNAFIL) pasan del tope de su horario. No depende de una empresa. Los días que revisa y la tolerancia están en Parámetros del sistema.',
            'ventana_defecto' => 0,
        ],
        'solicitudes_pendientes' => [
            'label' => 'Solicitudes pendientes de RRHH',
            'ayuda' => 'Solicitudes que llevan más de 24 horas esperando a RRHH.',
            'ventana_defecto' => 0,
        ],
    ];

    public const MODOS = [
        'dia_mes'    => 'Un día fijo de cada mes',
        'dias_antes' => 'Varios días antes del evento',
        'dia_semana' => 'Un día fijo de cada semana',
        'diario'     => 'Todos los días',
    ];

    public static function opciones(): array
    {
        return collect(self::TIPOS)->map(fn ($t) => $t['label'])->all();
    }

    /**
     * Rango de fechas [desde, hasta] que cubre el aviso de hoy.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rango(Recordatorio $r, Carbon $hoy): array
    {
        $hoy = $hoy->copy()->startOfDay();

        if ($r->modo === 'dias_antes') {
            $dia = $hoy->copy()->addDays((int) $r->dias_antes);

            return [$dia, $dia->copy()];
        }

        $ventana = $r->ventana_dias;
        if ($ventana === null) {
            // Aviso mensual sin ventana: lo que queda del mes en curso; el resto, la ventana por defecto del tipo.
            return $r->modo === 'dia_mes'
                ? [$hoy, $hoy->copy()->endOfMonth()->startOfDay()]
                : [$hoy, $hoy->copy()->addDays(self::TIPOS[$r->tipo]['ventana_defecto'] ?? 0)];
        }

        return [$hoy, $hoy->copy()->addDays((int) $ventana)];
    }

    /**
     * Lo que se avisa hoy para este recordatorio.
     *
     * @return array{titulo: string, intro: string, columnas: array, filas: array}
     */
    public static function recolectar(Recordatorio $r, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        [$desde, $hasta] = self::rango($r, $hoy);

        // La auditoría es global (todas las empresas): no necesita empresa ni consulta de trabajadores.
        if ($r->tipo === 'cruces_empresas') {
            return self::crucesEmpresas();
        }

        if ($r->tipo === 'horas_sobre_tope') {
            return self::horasSobreTope($hoy);
        }

        $base = Employee::where('company_id', $r->company_id)->where('active', true)->whereNull('fecha_cese')->with('department');

        return match ($r->tipo) {
            'contratos_por_vencer'   => self::contratos($base, $desde, $hasta, $hoy),
            'cumpleanos'             => self::cumpleanos($base, $desde, $hasta),
            'aniversarios'           => self::aniversarios($base, $desde, $hasta),
            'ceses_sin_liquidar'     => self::cesesSinLiquidar($r->company_id, $hoy),
            'solicitudes_pendientes' => self::solicitudesPendientes($r->company_id),
            default                  => ['titulo' => $r->tipo_label, 'intro' => '', 'columnas' => [], 'filas' => []],
        };
    }

    private static function crucesEmpresas(): array
    {
        [$filas] = app(\App\Services\AuditoriaCrucesService::class)->revisar();
        $errores = collect($filas)->where(0, 'ERROR')->map(fn ($f) => [$f[1], $f[2], $f[3]])->values()->all();

        return [
            'titulo'   => count($errores) . ' tipo(s) de cruce entre empresas por corregir',
            'intro'    => 'La revisión encontró datos que se cruzan entre empresas. Corre rrhh:auditar-cruces-empresa para ver el detalle y rrhh:corregir-areas-cruzadas para corregir áreas, sedes y horarios.',
            'columnas' => ['Revisión', 'Casos', 'Ejemplos'],
            'filas'    => $errores,
        ];
    }

    private static function parametro(string $clave, float $defecto): float
    {
        try {
            return (float) \App\Models\ParametroLegal::valor($clave);
        } catch (\Throwable) {
            return $defecto;
        }
    }

    /** Días con horas ordinarias por encima del tope de su horario (jornada menos refrigerio), de todas las empresas. */
    private static function horasSobreTope(Carbon $hoy): array
    {
        $dias      = (int) self::parametro('revision_horas_dias_atras', 7);
        $tolerancia = self::parametro('revision_horas_tolerancia', 0.02);
        $desde     = $hoy->copy()->subDays($dias);
        $hasta     = $hoy->copy()->subDay();

        $filas = [];
        $registros = \App\Models\AttendanceRecord::withoutGlobalScopes()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('horas_ordinarias', '>', 0)->orderBy('fecha')->get();

        $empleados = Employee::withoutGlobalScopes()->with(['schedule', 'company'])->whereIn('id', $registros->pluck('employee_id')->unique())->get()->keyBy('id');
        $conAdicionales = \Illuminate\Support\Facades\DB::table('employee_schedules')->pluck('employee_id')->flip();

        foreach ($registros as $reg) {
            $e = $empleados[$reg->employee_id] ?? null;
            $h = $e?->schedule;
            if (! $e || ! $h || $e->horas_por_marcacion || $e->exonerado_registro || isset($conAdicionales[$e->id])) {
                continue;
            }

            $jornada = strtotime($h->hora_salida) - strtotime($h->hora_entrada);
            if ($jornada <= 0) {
                $jornada += 86400; // turno nocturno
            }
            $refrigerio = $h->refrigerio_inicio && $h->refrigerio_fin ? max(0, strtotime($h->refrigerio_fin) - strtotime($h->refrigerio_inicio)) : 0;
            $tope = ($jornada - $refrigerio) / 3600;

            if ((float) $reg->horas_ordinarias > $tope + $tolerancia) {
                $fecha = $reg->fecha instanceof Carbon ? $reg->fecha : Carbon::parse($reg->fecha);
                $filas[] = [
                    $fecha->format('d/m/Y'), $e->apellidos . ', ' . $e->nombres, $e->company?->razon_social ?? '—',
                    number_format($tope, 2), number_format((float) $reg->horas_ordinarias, 2),
                    $reg->corregido_manualmente ? 'Sí' : 'No',
                ];
            }
        }

        return [
            'titulo'   => count($filas) . ' día(s) con horas ordinarias sobre el tope',
            'intro'    => 'Del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y') . ' hay días donde las horas ordinarias pasan del tope de su horario, y eso se vería en la columna Horas Trabajadas del reporte SUNAFIL. Si no están corregidos a mano, se arreglan reprocesando el rango; si lo están, con rrhh:recalcular-horas-corregidas.',
            'columnas' => ['Fecha', 'Trabajador', 'Empresa', 'Tope (h)', 'Ordinarias (h)', 'Corregido a mano'],
            'filas'    => $filas,
        ];
    }

    private static function rangoTexto(Carbon $desde, Carbon $hasta): string
    {
        return $desde->equalTo($hasta) ? 'el ' . $desde->format('d/m/Y') : 'del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
    }

    private static function contratos($base, Carbon $desde, Carbon $hasta, Carbon $hoy): array
    {
        $filas = (clone $base)->whereBetween('fecha_fin_contrato', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_fin_contrato')->get()
            ->map(fn ($e) => [
                $e->apellidos . ', ' . $e->nombres, $e->dni, $e->cargo ?? '—',
                $e->fecha_fin_contrato->format('d/m/Y'), $hoy->diffInDays($e->fecha_fin_contrato, false) . ' día(s)',
            ])->all();

        return [
            'titulo'   => count($filas) . ' contrato(s) por vencer',
            'intro'    => 'Contratos que vencen ' . self::rangoTexto($desde, $hasta) . '. Revisa si corresponde renovar o gestionar el cese a tiempo.',
            'columnas' => ['Trabajador', 'DNI', 'Cargo', 'Fin de contrato', 'Faltan'],
            'filas'    => $filas,
        ];
    }

    /** Trabajadores cuyo mes/día cae dentro del rango, con la fecha que les toca. */
    private static function porFechaAnual($base, string $campo, Carbon $desde, Carbon $hasta): array
    {
        $resultado = [];
        $empleados = (clone $base)->whereNotNull($campo)->get();

        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            foreach ($empleados as $e) {
                $f = $e->{$campo};
                if ($f->month === $d->month && $f->day === $d->day) {
                    $resultado[] = [$e, $d->copy(), $d->year - $f->year];
                }
            }
        }

        return $resultado;
    }

    private static function cumpleanos($base, Carbon $desde, Carbon $hasta): array
    {
        $filas = array_map(fn ($x) => [
            $x[0]->apellidos . ', ' . $x[0]->nombres, $x[0]->department?->nombre ?? '—', $x[1]->format('d/m/Y'), $x[2] . ' años',
        ], self::porFechaAnual($base, 'fecha_nacimiento', $desde, $hasta));

        return [
            'titulo'   => count($filas) . ' cumpleaños',
            'intro'    => 'Cumpleaños ' . self::rangoTexto($desde, $hasta) . '.',
            'columnas' => ['Trabajador', 'Área', 'Fecha', 'Cumple'],
            'filas'    => $filas,
        ];
    }

    private static function aniversarios($base, Carbon $desde, Carbon $hasta): array
    {
        $filas = array_values(array_map(fn ($x) => [
            $x[0]->apellidos . ', ' . $x[0]->nombres, $x[0]->fecha_ingreso->format('d/m/Y'), $x[1]->format('d/m/Y'), $x[2] . ' año(s) de servicio',
        ], array_filter(self::porFechaAnual($base, 'fecha_ingreso', $desde, $hasta), fn ($x) => $x[2] >= 1)));

        return [
            'titulo'   => count($filas) . ' aniversario(s) laboral(es)',
            'intro'    => 'Aniversarios de ingreso ' . self::rangoTexto($desde, $hasta) . '.',
            'columnas' => ['Trabajador', 'Ingreso', 'Aniversario', 'Cumple'],
            'filas'    => $filas,
        ];
    }

    private static function cesesSinLiquidar(int $companyId, Carbon $hoy): array
    {
        $filas = Employee::where('company_id', $companyId)
            ->whereNotNull('fecha_cese')
            ->where('fecha_cese', '>=', $hoy->copy()->subDays(60)->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('liquidaciones_cese')->whereColumn('liquidaciones_cese.employee_id', 'employees.id'))
            ->orderBy('fecha_cese')->get()
            ->map(fn ($e) => [$e->apellidos . ', ' . $e->nombres, $e->dni, $e->fecha_cese->format('d/m/Y'), $e->fecha_cese->diffInDays($hoy) . ' día(s)'])->all();

        return [
            'titulo'   => count($filas) . ' cese(s) sin liquidar',
            'intro'    => 'Trabajadores cesados en los últimos 60 días que aún no tienen liquidación por cese calculada.',
            'columnas' => ['Trabajador', 'DNI', 'Fecha de cese', 'Desde el cese'],
            'filas'    => $filas,
        ];
    }

    private static function solicitudesPendientes(int $companyId): array
    {
        $filas = Solicitud::with('employee')->where('company_id', $companyId)->where('estado', 'pendiente')
            ->where('created_at', '<=', now()->subDay())->orderBy('created_at')->get()
            ->map(fn ($s) => [$s->employee->nombre_completo, $s->tipo_label, $s->created_at->format('d/m/Y H:i'), $s->created_at->diffForHumans(null, true)])->all();

        return [
            'titulo'   => count($filas) . ' solicitud(es) pendiente(s)',
            'intro'    => 'Solicitudes que llevan más de 24 horas esperando la revisión de RRHH.',
            'columnas' => ['Trabajador', 'Tipo', 'Enviada', 'Esperando'],
            'filas'    => $filas,
        ];
    }
}
