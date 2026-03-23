<?php

namespace App\Http\Controllers;

use App\Helpers\CompanyContext;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanySelectController extends Controller
{
    public function show()
    {
        if (!auth()->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $companies = Company::where('active', true)->orderBy('razon_social')->get();
        return view('company-select', compact('companies'));
    }

    public function select(Request $request)
    {
        $request->validate(['company_id' => 'required|exists:companies,id']);

        CompanyContext::set((int) $request->company_id);

        return redirect('/admin');
    }
}
