<?php

namespace Otatechie\FilamentPaystackConnect\Tests\Fixtures\Resources;

use Filament\Resources\Pages\ViewRecord;
use Otatechie\FilamentPaystackConnect\Actions\ConnectPaystackAccountAction;

class ViewBusiness extends ViewRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [ConnectPaystackAccountAction::make()];
    }
}
