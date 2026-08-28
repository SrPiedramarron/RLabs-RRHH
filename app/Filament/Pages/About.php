<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Helpers\CompanyContext;

class About extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-information-circle';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.about';

    public function getCompany()
    {
        return CompanyContext::company();
    }
}