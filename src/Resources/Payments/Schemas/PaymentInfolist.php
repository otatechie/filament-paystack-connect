<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentStatusColor;
use Otatechie\PaystackConnect\Enums\PaymentStatus;
use Otatechie\PaystackConnect\Models\Payment;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->copyable()->fontFamily('mono'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => PaymentStatusColor::label($state))
                            ->color(fn (PaymentStatus $state): string => PaymentStatusColor::color($state)),
                        TextEntry::make('email')->label('Customer'),
                        TextEntry::make('subaccount.business_name')->label('Seller')->placeholder('Platform (no split)'),
                        TextEntry::make('payable_type')
                            ->label('Paid for')
                            ->state(fn (Payment $record): ?string => $record->getAttribute('payable_type') ? class_basename($record->getAttribute('payable_type')).' #'.$record->getAttribute('payable_id') : null)
                            ->placeholder('-'),
                        TextEntry::make('channel')->placeholder('-'),
                        TextEntry::make('paid_at')->dateTime()->placeholder('-'),
                        TextEntry::make('failure_reason')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Money')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('total')->state(fn (Payment $record): string => (string) $record->total()),
                        TextEntry::make('platform_fee')->label('Your fee')->state(fn (Payment $record): string => (string) $record->platformFee()),
                        TextEntry::make('seller_share')->label("Seller's share")->state(fn (Payment $record): string => (string) $record->sellerShare()),
                        TextEntry::make('paystack_fee')
                            ->label("Paystack's fee")
                            ->state(fn (Payment $record): ?string => $record->paystack_fee !== null ? $record->currency.' '.number_format($record->paystack_fee / 100, 2) : null)
                            ->placeholder('-'),
                        TextEntry::make('refunded')->label('Refunded')->state(fn (Payment $record): string => (string) $record->refundedAmount()),
                        TextEntry::make('pending_refunds')->label('Refund pending')->state(fn (Payment $record): string => (string) $record->pendingRefundAmount()),
                    ]),
            ]);
    }
}
