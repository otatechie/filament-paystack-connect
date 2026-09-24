<?php

namespace Otatechie\FilamentPaystackConnect\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\PanelProvider;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Resources\BusinessResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([BusinessResource::class])
            ->plugin(
                FilamentPaystackConnectPlugin::make()
                    ->sellerModel(Business::class, 'name')
                    ->sellerForm(fn () => [TextInput::make('name')->required()]),
            );
    }
}
