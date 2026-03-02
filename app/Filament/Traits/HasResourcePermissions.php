<?php

namespace App\Filament\Traits;

trait HasResourcePermissions
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('ver_' . static::getPermissionKey());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('crear_' . static::getPermissionKey());
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('editar_' . static::getPermissionKey());
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('eliminar_' . static::getPermissionKey());
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('eliminar_' . static::getPermissionKey());
    }

    // Cada Resource define su clave, ej: 'empleados', 'usuarios', etc.
    protected static function getPermissionKey(): string
    {
        return static::$permissionKey ?? '';
    }
}