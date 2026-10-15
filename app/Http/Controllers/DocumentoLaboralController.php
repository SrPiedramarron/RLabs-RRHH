<?php

namespace App\Http\Controllers;

use App\Helpers\CompanyContext;
use App\Models\Employee;
use App\Models\LiquidacionCese;
use App\Services\DocumentosLaboralesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Certificados y documentos laborales en PDF. */
class DocumentoLaboralController extends Controller
{
    public function __construct(private DocumentosLaboralesService $docs) {}

    private function autorizar(int $companyId): void
    {
        $actual = CompanyContext::get();
        abort_if($actual && (int) $actual !== $companyId, 403);
    }

    private function firma($empresa): array
    {
        return [
            'nombre' => $empresa->representante_legal ?: 'Representante legal',
            'cargo'  => $empresa->representante_legal_cargo ?: 'Gerente General',
        ];
    }

    private function archivo(string $prefijo, Employee $e): string
    {
        return $prefijo . '_' . str_replace(' ', '_', $e->apellidos) . '_' . str_replace(' ', '_', explode(' ', trim($e->nombres))[0]) . '.pdf';
    }

    // ── Certificado de trabajo ───────────────────────────────────────────────

    public function certificadoTrabajo(Request $request, Employee $employee)
    {
        $this->autorizar($employee->company_id);
        $employee->loadMissing('company');

        $emision = $request->filled('fecha') ? Carbon::parse($request->query('fecha')) : now();

        $pdf = Pdf::loadView('documentos.certificado-trabajo', [
            'e'         => $employee,
            'empresa'   => $employee->company,
            'docs'      => $this->docs,
            'trat'      => $this->docs->tratamiento($employee),
            'nombre'    => $this->docs->nombreCompleto($employee),
            'ingreso'   => $this->docs->fechaLarga($employee->fecha_ingreso),
            'cese'      => $employee->fecha_cese ? $this->docs->fechaLarga($employee->fecha_cese) : null,
            'ciudad'    => $this->docs->ciudad($employee->company),
            'emision'   => $this->docs->fechaLarga($emision),
            'firma'     => $this->firma($employee->company),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->archivo($employee->fecha_cese ? 'Certificado_de_trabajo' : 'Constancia_de_trabajo', $employee));
    }

    // ── Carta/certificado de cese para el retiro de CTS ──────────────────────

    public function cartaRetiroCts(Request $request, Employee $employee)
    {
        $this->autorizar($employee->company_id);
        abort_unless($employee->fecha_cese, 422, 'El trabajador no tiene fecha de cese en su ficha.');
        $employee->loadMissing('company');

        $emision = $request->filled('fecha') ? Carbon::parse($request->query('fecha')) : $employee->fecha_cese;

        $pdf = Pdf::loadView('documentos.carta-retiro-cts', [
            'e'        => $employee,
            'empresa'  => $employee->company,
            'trat'     => $this->docs->tratamiento($employee),
            'nombre'   => $this->docs->nombreCompleto($employee),
            'cese'     => $this->docs->fechaLarga($employee->fecha_cese),
            'ciudad'   => $this->docs->ciudad($employee->company),
            'emision'  => $this->docs->fechaLarga($emision),
            'banco'    => $employee->banco_cts,
            'cuenta'   => $employee->numero_cuenta_cts,
            'firma'    => $this->firma($employee->company),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->archivo('Certificado_de_cese_CTS', $employee));
    }

    // ── Certificado de rentas de quinta categoría ────────────────────────────

    public function certificadoQuinta(Request $request, Employee $employee)
    {
        $this->autorizar($employee->company_id);
        $employee->loadMissing('company');

        $anio = (int) ($request->query('anio') ?: now()->year);
        $base = $this->docs->quintaPrefill($employee, $anio);

        // Lo que RRHH haya ajustado en el formulario manda sobre el cálculo del sistema.
        $v = [];
        foreach (['sueldos', 'comisiones', 'utilidades', 'gratificacion', 'bonificaciones', 'remu_vacacional',
            'vac_truncas', 'grat_trunca', 'deduccion', 'retenciones', 'devolucion'] as $campo) {
            $v[$campo] = $request->filled($campo) ? round((float) $request->query($campo), 2) : $base[$campo];
        }

        $uit         = $base['uit'];
        $rentaBruta  = round($v['sueldos'] + $v['comisiones'] + $v['utilidades'] + $v['gratificacion']
            + $v['bonificaciones'] + $v['remu_vacacional'] + $v['vac_truncas'] + $v['grat_trunca'], 2);
        $rentaNeta   = round(max(0, $rentaBruta - $v['deduccion']), 2);
        $impuesto    = $this->docs->impuestoAnual($rentaNeta, $uit);
        $retenido    = round($v['retenciones'] - $v['devolucion'], 2);

        $fin = $employee->fecha_cese && $employee->fecha_cese->year === $anio ? $employee->fecha_cese : null;

        $pdf = Pdf::loadView('documentos.certificado-quinta', [
            'e'          => $employee,
            'empresa'    => $employee->company,
            'trat'       => $this->docs->tratamiento($employee),
            'nombre'     => $this->docs->nombreCompleto($employee),
            'anio'       => $anio,
            'v'          => $v,
            'uit'        => $uit,
            'rentaBruta' => $rentaBruta,
            'rentaNeta'  => $rentaNeta,
            'impuesto'   => $impuesto,
            'retenido'   => $retenido,
            'ciudad'     => $this->docs->ciudad($employee->company),
            'emision'    => $this->docs->fechaLarga($request->filled('fecha') ? Carbon::parse($request->query('fecha')) : now()),
            'firma'      => $this->firma($employee->company),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->archivo('Certificado_5ta_' . $anio, $employee));
    }

    // ── Documento de liquidación de beneficios sociales ──────────────────────

    public function liquidacion(Request $request, LiquidacionCese $liquidacion)
    {
        $this->autorizar($liquidacion->company_id);
        $liquidacion->loadMissing('employee', 'company');
        $e = $liquidacion->employee;

        $renta5ta   = round((float) $request->query('renta5ta', 0), 2);
        $devolucion = round((float) $request->query('devolucion5ta', 0), 2);
        $total      = round((float) $liquidacion->monto_total - $renta5ta + $devolucion, 2);

        [$anios, $meses, $dias] = $this->docs->tiempoServicio($e->fecha_ingreso ?? $liquidacion->fecha_cese, $liquidacion->fecha_cese);

        $emision = $request->filled('fecha') ? Carbon::parse($request->query('fecha')) : $liquidacion->fecha_cese;

        $pdf = Pdf::loadView('documentos.liquidacion', [
            'l'          => $liquidacion,
            'e'          => $e,
            'empresa'    => $liquidacion->company,
            'nombre'     => $this->docs->nombreCompleto($e),
            'afp'        => $this->docs->etiquetaPensiones($e->sistema_pensiones),
            'tiempo'     => "{$anios} AÑO" . ($anios === 1 ? '' : 'S') . " {$meses} MES" . ($meses === 1 ? '' : 'ES') . " {$dias} DÍA" . ($dias === 1 ? '' : 'S'),
            'renta5ta'   => $renta5ta,
            'devolucion' => $devolucion,
            'total'      => $total,
            'letras'     => $this->docs->montoEnLetras($total),
            'ciudad'     => mb_strtoupper($this->docs->ciudad($liquidacion->company)),
            'emision'    => mb_strtoupper($this->docs->fechaLarga($emision)),
            'firma'      => $this->firma($liquidacion->company),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->archivo('Liquidacion', $e));
    }
}
