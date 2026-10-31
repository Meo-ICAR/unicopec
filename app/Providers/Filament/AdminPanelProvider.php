<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RememberLastTenant;
use App\Models\Company;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->tenant(Company::class)
            // Dopo l'accesso si riparte dall'ultima società aperta.
            ->homeUrl(fn () => ($id = auth()->user()?->last_company_id) ? url("/admin/{$id}") : url('/admin'))
            ->tenantMenuItems([
                'select' => MenuItem::make()->label('Cambia società')->icon('heroicon-o-building-office'),
            ])
            ->tenantMiddleware([RememberLastTenant::class], isPersistent: true)
            ->navigationGroups([
                NavigationGroup::make('Configurazione'),
                NavigationGroup::make('Settings'),
                NavigationGroup::make('Manuali')->collapsible()->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Manuale utente')->group('Manuali')->icon('heroicon-o-book-open')->url(fn () => route('manuali.utente'))->openUrlInNewTab()->sort(1),
                NavigationItem::make('Manuale tecnico')->group('Manuali')->icon('heroicon-o-code-bracket-square')->url(fn () => route('manuali.tecnico'))->openUrlInNewTab()->sort(2),
            ])
            ->brandName('UnicoPEC')
            ->brandLogo(asset('images/unicopec-logo.png'))
            ->darkModeBrandLogo(asset('images/unicopec-logo-dark.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/unicopec-icon.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(
                FilamentSocialitePlugin::make()
                    ->providers([
                        Provider::make('microsoft')
                            ->label('Microsoft')
                            ->icon('fab-microsoft')
                            ->color(Color::hex('#0078D4'))
                            ->outlined(false)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(false)
                            ->stateless(false),
                    ])
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
