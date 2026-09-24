<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Otatechie\FilamentPaystackConnect\Actions\RefundPaymentAction;
use Otatechie\FilamentPaystackConnect\Actions\VerifyPaymentAction;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentResource;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VerifyPaymentAction::make(),
            RefundPaymentAction::make(),
        ];
    }
}
