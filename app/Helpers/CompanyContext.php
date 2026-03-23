<?php

namespace App\Helpers;

use App\Models\Company;

class CompanyContext
{
    const SESSION_KEY = 'active_company_id';

    public static function get(): ?int
    {
        return session(self::SESSION_KEY);
    }

    public static function set(int $companyId): void
    {
        session([self::SESSION_KEY => $companyId]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function company(): ?Company
    {
        $id = self::get();
        return $id ? Company::find($id) : null;
    }

    public static function isSet(): bool
    {
        return session()->has(self::SESSION_KEY);
    }
}
