<?php

namespace App\Services\PaymentFile;

use App\Services\PaymentFile\Data\PayrollBatch;
use App\Services\PaymentFile\Data\PayrollBeneficiary;

/**
 * Genera el archivo de carga masiva de haberes BBVA (formato de ancho fijo).
 *
 * Layout de posiciones/anchos reconstruido a partir de un archivo real
 * aceptado por el banco (BBVAHABE15.09.2026.txt, planilla del 15/09/2026).
 * El primer round-trip byte a byte contra esa muestra dio "match perfecto"
 * pero solo probaba consistencia interna (parseaba con una escala y
 * reexportaba con la misma escala) — el campo de importe en realidad usa
 * 2 decimales implícitos, no 3 como se asumió al inicio; quedó corregido
 * y reverificado contra los montos reales que RRHH confirmó que se
 * pagaron esa quincena (ver comentario en buildDetail). Los códigos fijos
 * de la cabecera marcados abajo (código de producto '503', número de
 * lote, indicador 'N') no varían entre beneficiarios en la muestra y no
 * se derivan de ningún dato de PayrollRun — se replican tal cual el
 * archivo que el banco aceptó. Si BBVA cambia esos códigos para otra
 * empresa/contrato, hay que ajustarlos aquí.
 */
class BbvaHaberesExporter
{
    private const HEADER_LENGTH = 159;
    private const DETAIL_LENGTH = 233;

    private const CODIGO_PRODUCTO = '503';
    private const INDICADOR = 'N';

    public function export(PayrollBatch $batch): string
    {
        $lines = [$this->buildHeader($batch)];

        foreach ($batch->beneficiaries as $beneficiary) {
            $lines[] = $this->buildDetail($beneficiary);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    private function buildHeader(PayrollBatch $batch): string
    {
        $line = str_pad(preg_replace('/\D/', '', $batch->cuentaCargo), 20, '0', STR_PAD_LEFT)
            . self::CODIGO_PRODUCTO
            . str_pad($batch->moneda, 3)
            . str_pad((string) $batch->numeroLote, 15, '0', STR_PAD_LEFT)
            . $batch->tipoProceso
            . str_repeat(' ', 9)
            . str_pad($this->normalizarTexto($batch->referencia), 25)
            . str_pad((string) $batch->numeroDeLotes, 6, '0', STR_PAD_LEFT)
            . self::INDICADOR
            . str_repeat('0', 18)
            . str_repeat(' ', 58);

        return $this->fixLength($line, self::HEADER_LENGTH);
    }

    private function buildDetail(PayrollBeneficiary $beneficiary): string
    {
        // CORREGIDO: el campo de 15 dígitos tiene 2 decimales implícitos
        // (no 3). Verificado contra los montos realmente pagados en la
        // planilla de RRHH de set. 2026 (Orihuela S/4,600.00, Martínez
        // S/2,700.00, Ricra S/790.00) — con ×1000 el archivo generado
        // habría pagado 10 veces menos de lo debido.
        $importe = str_pad((string) (int) round($beneficiary->importe * 100), 15, '0', STR_PAD_LEFT);

        $line = '002'
            . $beneficiary->doiTipo
            . str_pad(preg_replace('/\D/', '', $beneficiary->doiNumero), 12)
            . $beneficiary->tipoAbono
            . str_pad(preg_replace('/\D/', '', $beneficiary->cuenta), 20)
            . str_pad($this->normalizarTexto($beneficiary->nombre), 40)
            . $importe
            . str_repeat(' ', 141);

        return $this->fixLength($line, self::DETAIL_LENGTH);
    }

    /**
     * BBVA exige texto plano (sin tildes/ñ) en mayúsculas — el archivo de
     * referencia trae caracteres corruptos donde el original tenía 'Ñ',
     * señal de que el banco espera ASCII puro.
     */
    private function normalizarTexto(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'U'],
            $value
        );

        return preg_replace('/[^A-Z0-9 ]/', '', $value);
    }

    private function fixLength(string $line, int $length): string
    {
        return str_pad(substr($line, 0, $length), $length);
    }
}
