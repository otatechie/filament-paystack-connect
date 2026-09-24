<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages;

use Filament\Resources\Pages\ViewRecord;
use Otatechie\FilamentPaystackConnect\Actions\ConnectSellerAction;
use Otatechie\FilamentPaystackConnect\Actions\LinkSubaccountAction;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\SellerResource;

class ViewSeller extends ViewRecord
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConnectSellerAction::forRecord(),
            LinkSubaccountAction::make(),
        ];
    }
}
