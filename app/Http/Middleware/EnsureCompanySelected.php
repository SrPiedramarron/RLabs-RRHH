<?php

namespace App\Http\Middleware;

use App\Helpers\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next)
    {
        // No aplicar a rutas del checkin (app móvil) ni logout
        if ($request->is('checkin*') || $request->is('admin/logout')) {
            return $next($request);
        }

        if (auth()->check() && !CompanyContext::isSet()) {
            if (!$request->is('select-company*')) {
                return redirect()->route('company.show');
            }
        }

        return $next($request);
    }
}
