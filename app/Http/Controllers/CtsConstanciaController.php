<?php

namespace App\Http\Controllers;

use App\Helpers\CompanyContext;
use App\Models\CtsDeposito;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Constancias de depósito de CTS (una por trabajador, una página cada una). */
class CtsConstanciaController extends Controller
{
    public function individual(Request $request, CtsDeposito $deposito)
    {
        $this->autorizar($deposito->company_id);

        $pdf = $this->generar(collect([$deposito]), $request->query('fecha'));

        return $pdf->download('Constancia_CTS_' . str_replace(' ', '_', $deposito->apellidos) . '_' . $deposito->periodo . '.pdf');
    }

    public function lote(Request $request, int $companyId, string $tipo, int $anio)
    {
        $this->autorizar($companyId);

        $depositos = CtsDeposito::with('employee.department', 'company')
            ->where('company_id', $companyId)
            ->where('tipo', $tipo)
            ->where('anio', $anio)
            ->orderBy('apellidos')
            ->get();

        abort_if($depositos->isEmpty(), 404, 'No hay CTS calculada para ese periodo. Calcúlala primero.');

        return $this->generar($depositos, $request->query('fecha'))
            ->download('Constancias_CTS_' . $tipo . '_' . $anio . '.pdf');
    }

    private function autorizar(int $companyId): void
    {
        $actual = CompanyContext::get();
        abort_if($actual && (int) $actual !== $companyId, 403);
    }

    private function generar($depositos, ?string $fecha)
    {
        $depositos = \Illuminate\Database\Eloquent\Collection::make($depositos)->loadMissing('employee.department', 'company');

        $filas = $depositos->map(function (CtsDeposito $d) use ($fecha) {
            $anio = (int) $d->anio;
            [$desde, $hasta] = $d->tipo === 'mayo'
                ? [Carbon::create($anio - 1, 11, 1), Carbon::create($anio, 4, 30)]
                : [Carbon::create($anio, 5, 1), Carbon::create($anio, 10, 31)];

            $e = $d->employee;

            return [
                'd'           => $d,
                'empresa'     => $d->company?->razon_social,
                'ruc'         => $d->company?->ruc,
                'rep_nombre'  => $d->company?->representante_legal,
                'rep_cargo'   => $d->company?->representante_legal_cargo ?: 'Representante legal',
                'empleado'    => trim($d->apellidos . ' ' . $d->nombres),
                'ingreso'     => $e?->fecha_ingreso?->format('d/m/Y'),
                'area'        => $e?->department?->nombre,
                'puesto'      => $d->cargo,
                'desde'       => $desde->format('d/m/Y'),
                'hasta'       => $hasta->format('d/m/Y'),
                'deposito'    => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null,
                'banco'       => $e?->banco_cts,
                'cuenta'      => $e?->numero_cuenta_cts,
                'rem'         => (float) $d->remuneracion_computable,
                'meses'       => (int) $d->meses_computables,
                'dias'        => (int) $d->dias_computables,
                'por_meses'   => round((float) $d->remuneracion_computable / 12 * $d->meses_computables, 2),
                'por_dias'    => round((float) $d->remuneracion_computable / 12 / 30 * $d->dias_computables, 2),
                'total'       => (float) $d->monto_cts,
            ];
        });

        return Pdf::loadView('cts.constancia', ['filas' => $filas])->setPaper('a4', 'portrait');
    }
}
