<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Blade;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            // ─── BRANDING ────────────────────────────────────────────────
            ->brandName('Control de Asistencia - InProcess')
            ->brandLogo(asset('images/inprocess-logo.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/inprocess-favicon.svg'))

            // ─── COLORES ─────────────────────────────────────────────────
            ->colors([
                'primary'   => Color::hex('#E30613'),   // Rojo InProcess
                'gray'      => Color::hex('#494B48'),   // Gris oscuro InProcess
            ])

            // ─── FOOTER PERSONALIZADO ────────────────────────────────────
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => Blade::render('
                    <div style="
                        text-align: center;
                        padding: 1rem;
                        font-size: 0.75rem;
                        color: #6b7280;
                        border-top: 1px solid #e5e7eb;
                        background: #f9fafb;
                    ">
                        © 2026 InProcess Perú &mdash;
                        <span style="color: #E30613; font-weight: 600;">Powered by RLabs</span>
                    </div>
                '),
            )

            // ─── LOGIN PAGE ───────────────────────────────────────────────
            ->login()

            // ─── NAVEGACIÓN ───────────────────────────────────────────────
            ->sidebarCollapsibleOnDesktop()

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\AttendancePieChart::class,
                \App\Filament\Widgets\HoursPieChart::class,
                \App\Filament\Widgets\WorkedDaysChart::class,
                \App\Filament\Widgets\LatenessChart::class,
            ])
            ->plugin(FilamentShieldPlugin::make())
            ->plugin(FilamentApexChartsPlugin::make())
            
            // ─── MIDDLEWARE ───────────────────────────────────────────────
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}