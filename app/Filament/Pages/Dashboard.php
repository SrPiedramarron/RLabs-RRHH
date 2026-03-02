<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Resources\StatisticsResource\Widgets\TardanzaResumenWidget::class,
            // aquí puedes agregar otros widgets que ya tengas
        ];
    }
}