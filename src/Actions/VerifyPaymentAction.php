<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Payment;

/** Asks Paystack for a payment's status, for payments not yet settled. */
class VerifyPaymentAction
{
    public static function make(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Payment $record): bool => ! $record->status->isFinal())
            ->authorize(fn (Payment $record): bool => FilamentPaystackConnectPlugin::allows('verify', $record))
            ->action(function (Payment $record): void {
                try {
                    $payment = PaystackConnect::verify($record->reference);
                } catch (PaystackException $e) {
                    Notification::make()->title('Paystack could not verify this payment')->body(FilamentPaystackConnectPlugin::errorMessage($e))->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Paystack says: '.($payment?->status->value ?? 'unknown'))
                    ->success()
                    ->send();
            });
    }
}
