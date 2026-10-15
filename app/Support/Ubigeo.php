<?php

namespace App\Support;

/**
 * Ubigeo del Perú (INEI): departamentos, provincias y distritos con sus
 * códigos de 2, 4 y 6 dígitos. Los datos están en database/data/ubigeo_peru.json.
 */
class Ubigeo
{
    private static ?array $datos = null;

    private static function datos(): array
    {
        return self::$datos ??= json_decode(file_get_contents(database_path('data/ubigeo_peru.json')), true);
    }

    /** [código => nombre] */
    public static function departamentos(): array
    {
        return self::datos()['departamentos'];
    }

    /** Provincias de un departamento. */
    public static function provincias(?string $departamento): array
    {
        return $departamento ? (self::datos()['provincias'][$departamento] ?? []) : [];
    }

    /** Distritos de una provincia. */
    public static function distritos(?string $provincia): array
    {
        return $provincia ? (self::datos()['distritos'][$provincia] ?? []) : [];
    }

    public static function nombreDepartamento(?string $codigo): ?string
    {
        return $codigo ? (self::departamentos()[$codigo] ?? null) : null;
    }

    public static function nombreProvincia(?string $codigo): ?string
    {
        return $codigo ? (self::provincias(substr($codigo, 0, 2))[$codigo] ?? null) : null;
    }

    public static function nombreDistrito(?string $codigo): ?string
    {
        return $codigo ? (self::distritos(substr($codigo, 0, 4))[$codigo] ?? null) : null;
    }
}
