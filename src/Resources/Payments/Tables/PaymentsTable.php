<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Otatechie\FilamentPaystackConnect\Actions\RefundPaymentAction;
use Otatechie\FilamentPaystackConnect\Actions\VerifyPaymentAction;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentStatusColor;
use Otatechie\PaystackConnect\Enums\PaymentStatus;
use Otatechie\PaystackConnect\Models\Payment;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => PaymentStatusColor::label($state))
                    ->color(fn (PaymentStatus $state): string => PaymentStatusColor::color($state)),
                TextColumn::make('subaccount.business_name')
                    ->label('Seller')
                    ->placeholder('Platform'),
                TextColumn::make('email')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (Payment $record): string => (string) $record->total())
                    ->sortable(),
                TextColumn::make('platform_fee')
                    ->label('Your fee')
                    ->formatStateUsing(fn (Payment $record): string => (string) $record->platformFee())
                    ->sortable(),
                TextColumn::make('refunded_amount')
                    ->label('Refunded')
                    ->formatStateUsing(fn (Payment $record): string => $record->refunded_amount ? (string) $record->refundedAmount() : '')
                    ->toggleable(),
                TextColumn::make('channel')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PaymentStatusColor::options()),
                SelectFilter::make('subaccount')
                    ->label('Seller')
                    ->relationship('subaccount', 'business_name'),
            ])
            ->recordActions([
                ViewAction::make(),
                VerifyPaymentAction::make(),
                RefundPaymentAction::make(),
            ]);
    }
}
