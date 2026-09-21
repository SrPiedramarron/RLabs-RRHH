<?php

namespace App\Services\PaymentFile\Data;

/**
 * Datos de cabecera de un lote de pago (una quincena/planilla completa).
 *
 * @param PayrollBeneficiary[] $beneficiaries
 */
final class PayrollBatch
{
    public function __construct(
        public readonly string $cuentaCargo,   // cuenta de cargo BBVA de la empresa (numérica, sin guiones)
        public readonly string $moneda,        // 'PEN' | 'USD'
        public readonly string $tipoProceso,   // 'A' = inmediato, 'F' = fecha futura, 'H' = horario
        public readonly string $referencia,    // ej. "1ERA QUINCENA SETIEMBRE 2026" (se trunca a 25 chars)
        public readonly array $beneficiaries,
        // Ambos sin significado confirmado por spec del banco — se dejan configurables
        // por si hace falta variarlos, pero en la muestra real (BBVAHABE15.09.2026.txt)
        // siempre fueron 1. Ver BbvaHaberesExporter.
        public readonly int $numeroLote = 1,
        public readonly int $numeroDeLotes = 1,
        // Fecha de pago — solo la usa BcpHaberesExporter (va en la cabecera, AAAAMMDD).
        public readonly ?\DateTimeInterface $fechaPago = null,
    ) {
    }

    public function montoTotal(): float
    {
        return array_sum(array_map(fn (PayrollBeneficiary $b) => $b->importe, $this->beneficiaries));
    }
}
