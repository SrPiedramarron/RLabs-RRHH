<?php

namespace App\Support;

/**
 * Tabla 21 SUNAT: "Tipo de Suspensión de la Relación Laboral".
 * Fuente: Anexo 2 - Tablas Paramétricas PLAME (oficial SUNAT).
 * Códigos 1-8 = Suspensión Perfecta (S.P.), códigos 20-27 = Suspensión Imperfecta (S.I.).
 */
class Tabla21Suspension
{
    const OPCIONES = [
        '1'  => 'S.P. Sanción disciplinaria',
        '2'  => 'S.P. Ejercicio del derecho de huelga',
        '3'  => 'S.P. Detención del trabajador (salvo condena privativa de libertad)',
        '4'  => 'S.P. Inhabilitación administrativa o judicial (hasta 3 meses)',
        '5'  => 'S.P. Permiso o licencia sin goce de haber',
        '6'  => 'S.P. Caso fortuito o fuerza mayor',
        '7'  => 'S.P. Falta no justificada',
        '8'  => 'S.P. Por temporada o intermitente',
        '20' => 'S.I. Enfermedad o accidente (primeros 20 días)',
        '21' => 'S.I. Incapacidad temporal (invalidez, enfermedad, accidente) — subsidiado EsSalud',
        '22' => 'S.I. Maternidad (descanso pre y post natal) — subsidiado EsSalud',
        '23' => 'S.I. Descanso vacacional',
        '24' => 'S.I. Licencia por cargo cívico / servicio militar obligatorio',
        '25' => 'S.I. Permiso o licencia por cargo sindical',
        '26' => 'S.I. Licencia con goce de haber',
        '27' => 'S.I. Días compensados por horas extra',
    ];

    /** Códigos 21 y 22 son los únicos que cuentan como "días subsidiados" ante SUNAT. */
    const CODIGOS_SUBSIDIADOS = ['21', '22'];

    public static function label(?string $codigo): string
    {
        return self::OPCIONES[$codigo] ?? '—';
    }
}
