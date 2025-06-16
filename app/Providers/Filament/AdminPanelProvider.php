<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BestSeller;
use App\Filament\Widgets\PenjualanChart;
use App\Filament\Widgets\PenjualanChartWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\Setting;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use Nuxtifyts\DashStackTheme\DashStackThemePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $setting = Setting::first();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName($setting->site_name)
            ->globalSearch(true)
            ->spa()
            ->sidebarCollapsibleOnDesktop(true)
            ->breadcrumbs(true)
            ->sidebarWidth('15rem')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->favicon(asset('storage/' . $setting->logo))
            ->brandLogo(asset('storage/' . $setting->logo))
            ->brandLogoHeight('5rem')
            ->font('Poppins', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => '#1b55f6',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                StatsOverview::class,
                PenjualanChart::class,
                BestSeller::class,
            ])
            ->navigationItems([
                NavigationItem::make('Kasir')
                    ->url(fn(): string => route('cashier.index'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-computer-desktop')
                    ->group('Penjualan')
                    ->sort(80),

            ])
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
            ->theme(asset('css/filament/admin/theme.css'))
            ->plugins([
                FilamentApexChartsPlugin::make()
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
