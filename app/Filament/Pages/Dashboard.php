<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\AtencionRequeridaWidget::class,
            \App\Filament\Widgets\PlanillaMesWidget::class,
            \App\Filament\Widgets\PersonalWidget::class,
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Resources\StatisticsResource\Widgets\TardanzaResumenWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
