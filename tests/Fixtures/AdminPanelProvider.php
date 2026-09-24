<?php

namespace Otatechie\FilamentPaystackConnect\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentPaystackConnectPlugin::make()->sellerModel(Business::class, 'name'));
    }
}
