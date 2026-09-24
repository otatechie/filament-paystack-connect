<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments\Pages;

use Filament\Resources\Pages\ListRecords;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentResource;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
