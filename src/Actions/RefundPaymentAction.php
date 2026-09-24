<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Payment;
use Otatechie\PaystackConnect\Support\Money;

/** Refunds a successful payment in full or in part. */
class RefundPaymentAction
{
    public static function make(): Action
    {
        return Action::make('refund')
            ->modalWidth(Width::Medium)
            ->label('Refund')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->isSuccessful() && ! $record->refundableAmount()->isZero())
            ->authorize(fn (Payment $record): bool => FilamentPaystackConnectPlugin::allows('refund', $record))
            ->modalHeading('Refund payment')
            ->modalSubmitActionLabel('Refund')
            ->modalDescription('Paystack returns the money to the customer. The payment updates when Paystack confirms the refund.')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount')
                    ->helperText(fn (Payment $record): string => "Leave empty to refund everything left: {$record->refundableAmount()}.")
                    ->numeric()
                    ->minValue(0.01),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    $amount = filled($data['amount'] ?? null) ? Money::major((string) $data['amount'], $record->currency) : null;

                    PaystackConnect::refund($record, $amount);
                } catch (PaystackException|InvalidArgumentException $e) {
                    Notification::make()->title('The refund was not requested')->body(FilamentPaystackConnectPlugin::errorMessage($e))->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Refund of {$record->refresh()->pendingRefundAmount()} requested")
                    ->body('Paystack will process it shortly.')
                    ->success()
                    ->send();
            });
    }
}
