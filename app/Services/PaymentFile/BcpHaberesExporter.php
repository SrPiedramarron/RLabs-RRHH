<?php

namespace App\Services\PaymentFile;

use App\Services\PaymentFile\Data\PayrollBatch;
use App\Services\PaymentFile\Data\PayrollBeneficiary;

/**
 * Genera el archivo de carga masiva de haberes BCP (formato de ancho fijo).
 *
 * Layout reconstruido byte a byte a partir de un archivo real aceptado por el
 * banco (HABERES20260915BCP.txt, planilla del 15/09/2026). Todos los campos
 * de cada beneficiario, y el monto total y la fecha de la cabecera, están
 * verificados contra esa muestra (el monto total de la cabecera coincide
 * exactamente con la suma de los 21 abonos de detalle). El código final de la
 * cabecera (pos. 98-112, 15 dígitos) no se pudo derivar de ningún dato de
 * PayrollRun — se replica tal cual el archivo que RRHH confirmó que el banco
 * aceptó. Si BCP cambia ese código para otra empresa/convenio, hay que
 * ajustarlo aquí.
 *
 * A diferencia de BBVA, esta muestra solo tiene transferencias a cuentas
 * propias BCP (cuenta de 14 dígitos, mismo banco que la empresa). No hay
 * ningún registro interbancario (CCI) en el archivo de referencia, así que
 * el mapeo de trabajadores con banco distinto a BCP no está implementado
 * — se excluyen con advertencia hasta tener una muestra real de ese caso.
 */
class BcpHaberesExporter
{
    private const HEADER_LENGTH = 113;
    private const DETAIL_LENGTH = 195;

    private const HEADER_PREFIJO = '1000020';
    private const HEADER_CODIGO_TIPO = 'XC';
    private const HEADER_CODIGO_RELLENO = '000';
    private const HEADER_CODIGO_FINAL = '000930742422962';

    private const DETAIL_TIPO_REGISTRO = '2A';
    private const DETAIL_TIPO_DOC_DNI = '1';
    private const DETAIL_PREFIJO_MONTO = '0001';

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
        if (! $batch->fechaPago) {
            throw new \InvalidArgumentException('BcpHaberesExporter requiere PayrollBatch::$fechaPago.');
        }

        $montoTotal = number_format($batch->montoTotal(), 2, '.', '');
        [$entero, $decimales] = explode('.', $montoTotal);

        $line = self::HEADER_PREFIJO
            . $batch->fechaPago->format('Ymd')
            . self::HEADER_CODIGO_TIPO
            . self::HEADER_CODIGO_RELLENO
            . str_pad(preg_replace('/\D/', '', $batch->cuentaCargo), 14, '0', STR_PAD_LEFT)
            . str_repeat(' ', 7)
            . str_pad($entero, 14, '0', STR_PAD_LEFT)
            . '.'
            . str_pad($decimales, 2, '0', STR_PAD_LEFT)
            . str_pad(substr(trim($batch->referencia), 0, 40), 40)
            . self::HEADER_CODIGO_FINAL;

        return $this->fixLength($line, self::HEADER_LENGTH);
    }

    private function buildDetail(PayrollBeneficiary $beneficiary): string
    {
        $dni = str_pad(preg_replace('/\D/', '', $beneficiary->doiNumero), 8, '0', STR_PAD_LEFT);
        $montoTotal = number_format($beneficiary->importe, 2, '.', '');
        [$entero, $decimales] = explode('.', $montoTotal);

        $line = self::DETAIL_TIPO_REGISTRO
            . str_pad(preg_replace('/\D/', '', $beneficiary->cuenta), 14, '0', STR_PAD_LEFT)
            . str_repeat(' ', 6)
            . self::DETAIL_TIPO_DOC_DNI
            . $dni
            . str_repeat(' ', 7)
            . str_pad($this->normalizarTexto($beneficiary->nombre), 75)
            . 'Referencia Beneficiario '
            . $dni
            . str_repeat(' ', 8)
            . 'Ref Emp '
            . $dni
            . str_repeat(' ', 4)
            . self::DETAIL_PREFIJO_MONTO
            . str_pad($entero, 14, '0', STR_PAD_LEFT)
            . '.'
            . str_pad($decimales, 2, '0', STR_PAD_LEFT)
            . 'S';

        return $this->fixLength($line, self::DETAIL_LENGTH);
    }

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
