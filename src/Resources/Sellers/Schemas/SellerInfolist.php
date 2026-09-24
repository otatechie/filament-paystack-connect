<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Sellers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Otatechie\PaystackConnect\Enums\PaymentStatus;
use Otatechie\PaystackConnect\Models\Subaccount;
use Otatechie\PaystackConnect\Support\Money;

class SellerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('business_name')->label('Seller'),
                        TextEntry::make('subaccount_code')->label('Subaccount')->copyable()->fontFamily('mono'),
                        TextEntry::make('account')
                            ->state(fn (Subaccount $record): string => trim(($record->bank_name ?? $record->settlement_bank ?? '').' '.$record->maskedAccountNumber())),
                        TextEntry::make('account_name')->label('Holder')->placeholder('-'),
                        TextEntry::make('currency'),
                        IconEntry::make('active')->boolean(),
                    ]),
                Section::make('Earnings')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('paid_count')
                            ->label('Successful payments')
                            ->state(fn (Subaccount $record): int => $record->payments()->where('status', PaymentStatus::Success)->count()),
                        TextEntry::make('seller_total')
                            ->label("Seller's share, before refunds")
                            ->state(function (Subaccount $record): string {
                                $paid = $record->payments()->whereIn('status', [PaymentStatus::Success, PaymentStatus::Refunded]);

                                return (string) Money::minor((int) $paid->sum('amount') - (int) $paid->sum('platform_fee'), $record->currency);
                            }),
                    ]),
            ]);
    }
}
