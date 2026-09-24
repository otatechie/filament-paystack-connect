<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages;

use Filament\Resources\Pages\ListRecords;
use Otatechie\FilamentPaystackConnect\Actions\ConnectSellerAction;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\SellerResource;

class ListSellers extends ListRecords
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConnectSellerAction::make(),
        ];
    }
}
