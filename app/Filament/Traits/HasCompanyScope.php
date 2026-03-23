<?php
namespace App\Filament\Traits;

use App\Helpers\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait HasCompanyScope
{
    public static function getEloquentQuery(): Builder
    {
        $query     = parent::getEloquentQuery();
        $companyId = CompanyContext::get();

        if ($companyId) {
            return $query->where('company_id', $companyId);
        }

        return $query;
    }
}
