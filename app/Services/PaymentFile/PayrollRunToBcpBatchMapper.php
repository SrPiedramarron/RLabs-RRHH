<?php

namespace App\Services\PaymentFile;

use App\Models\PayrollRun;
use App\Services\PaymentFile\Data\PayrollBatch;
use App\Services\PaymentFile\Data\PayrollBeneficiary;

/**
 * Arma el PayrollBatch para BcpHaberesExporter a partir de un PayrollRun.
 *
 * Solo incluye trabajadores con cuenta PROPIA de BCP (banco = 'BCP' y
 * tipo_cuenta_pago = 'propia') — es el único caso confirmado contra un
 * archivo real (ver BcpHaberesExporter). Trabajadores con otro banco
 * (transferencia interbancaria vía CCI) se excluyen con advertencia hasta
 * tener una muestra real de ese formato.
 */
class PayrollRunToBcpBatchMapper
{
    /**
     * @return array{0: PayrollBatch, 1: string[]}
     */
    public function map(PayrollRun $run): array
    {
        if (empty($run->cuenta_cargo_pago)) {
            throw new \InvalidArgumentException('La planilla no tiene una cuenta de cargo (cuenta_cargo_pago) configurada.');
        }

        $entries = $run->entries()->with('employee')->get();

        $beneficiaries = [];
        $warnings = [];

        foreach ($entries as $entry) {
            $employee = $entry->employee;

            if (! $employee) {
                $warnings[] = "Entry #{$entry->id}: sin trabajador asociado.";

                continue;
            }

            if (empty($employee->cuenta_pago) || empty($employee->tipo_cuenta_pago)) {
                $warnings[] = "{$employee->nombre_completo}: sin cuenta bancaria registrada.";

                continue;
            }

            if (strtoupper((string) $employee->banco) !== 'BCP' || $employee->tipo_cuenta_pago !== 'propia') {
                $warnings[] = "{$employee->nombre_completo}: no tiene cuenta propia BCP (transferencias interbancarias por BCP aún no soportadas).";

                continue;
            }

            if ((float) $entry->monto_neto <= 0) {
                $warnings[] = "{$employee->nombre_completo}: monto neto en cero o negativo, excluido.";

                continue;
            }

            $beneficiaries[] = new PayrollBeneficiary(
                doiTipo: 'L',
                doiNumero: (string) $employee->dni,
                tipoAbono: 'P',
                cuenta: $employee->cuenta_pago,
                nombre: $employee->nombre_completo_normal,
                importe: (float) $entry->monto_neto,
            );
        }

        $batch = new PayrollBatch(
            cuentaCargo: $run->cuenta_cargo_pago,
            moneda: $run->moneda ?: 'PEN',
            tipoProceso: 'A',
            referencia: $run->referencia ?: '',
            beneficiaries: $beneficiaries,
            fechaPago: $run->fecha_pago,
        );

        return [$batch, $warnings];
    }
}
