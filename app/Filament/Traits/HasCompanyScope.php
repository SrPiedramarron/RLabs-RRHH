<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasCompanyScope
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->isSuperAdmin() || is_null($user->company_id)) {
            return $query;
        }

        return $query->where('company_id', $user->company_id);
    }
}