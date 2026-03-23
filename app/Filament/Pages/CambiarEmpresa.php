<?php

namespace App\Filament\Pages;

use App\Helpers\CompanyContext;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Forms\Components\Radio;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class CambiarEmpresa extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Cambiar Empresa';
    protected static ?string $title           = 'Cambiar Empresa';
    protected static ?int    $navigationSort  = 99;
    protected static string  $view            = 'filament.pages.cambiar-empresa';

    public ?int $company_id = null;

    public function mount(): void
    {
        $this->company_id = CompanyContext::get();
    }

    public function getCompaniesProperty()
    {
        return Company::where('active', true)->orderBy('razon_social')->get();
    }

    public function cambiar(int $companyId): void
    {
        CompanyContext::set($companyId);
        $company = Company::find($companyId);

        Notification::make()
            ->title("Empresa cambiada a {$company->razon_social}")
            ->success()
            ->send();

        $this->redirect('/admin');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
