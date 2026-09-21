<?php

namespace App\Services\PaymentFile;

use App\Models\PayrollRun;
use App\Services\PaymentFile\Data\PayrollBatch;
use App\Services\PaymentFile\Data\PayrollBeneficiary;

/**
 * Arma el PayrollBatch (DTO plano) a partir de un PayrollRun y sus entries,
 * para que BbvaHaberesExporter no dependa de los modelos Eloquent.
 *
 * Solo incluye trabajadores con cuenta bancaria registrada (banco + cuenta_pago
 * + tipo_cuenta_pago en Employee). Los que no la tienen se excluyen y se listan
 * en $warnings para que RRHH complete sus datos antes de reintentar.
 */
class PayrollRunToBbvaBatchMapper
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

            if (empty($employee->banco) || empty($employee->cuenta_pago) || empty($employee->tipo_cuenta_pago)) {
                $warnings[] = "{$employee->nombre_completo}: sin cuenta bancaria registrada.";

                continue;
            }

            if ((float) $entry->monto_neto <= 0) {
                $warnings[] = "{$employee->nombre_completo}: monto neto en cero o negativo, excluido.";

                continue;
            }

            $beneficiaries[] = new PayrollBeneficiary(
                doiTipo: $employee->doi_tipo_bancario ?: 'L',
                doiNumero: (string) $employee->dni,
                tipoAbono: $employee->tipo_cuenta_pago === 'cci' ? 'I' : 'P',
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
        );

        return [$batch, $warnings];
    }
}
