<?php

namespace App\Services\PaymentFile\Data;

/**
 * Un abono individual dentro de la planilla (un trabajador).
 *
 * Nace de PayrollEntry/Worker de SumaRH; se arma explícitamente aquí
 * para que el exportador no dependa de los modelos Eloquent y sea
 * fácil de testear con fixtures planos.
 */
final class PayrollBeneficiary
{
    public function __construct(
        public readonly string $doiTipo,     // 'L' = DNI, 'C' = Carné Extranjería, etc.
        public readonly string $doiNumero,   // sin ceros a la izquierda forzados; se hace padding al exportar
        public readonly string $tipoAbono,   // 'P' = cuenta propia BBVA validada, 'I' = interbancario (CCI), etc.
        public readonly string $cuenta,      // cuenta BBVA (20) o CCI interbancario (20)
        public readonly string $nombre,      // nombre completo del trabajador
        public readonly float $importe,      // en soles/dólares, con decimales (ej. 460.00)
    ) {
    }
}
