<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Sellers\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Otatechie\FilamentPaystackConnect\Actions\ConnectSellerAction;
use Otatechie\FilamentPaystackConnect\Actions\LinkSubaccountAction;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Models\Subaccount;

class SellersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('owner')->withCount('payments'))
            ->columns([
                TextColumn::make('business_name')
                    ->label('Seller')
                    ->searchable()
                    ->description(fn (Subaccount $record): ?string => static::ownerLabel($record)),
                TextColumn::make('account')
                    ->label('Account')
                    ->state(fn (Subaccount $record): string => trim(($record->bank_name ?? $record->settlement_bank ?? '').' '.$record->maskedAccountNumber())),
                TextColumn::make('account_name')
                    ->label('Holder')
                    ->placeholder('-'),
                TextColumn::make('currency'),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('payments_count')
                    ->label('Payments')
                    ->sortable(),
                TextColumn::make('subaccount_code')
                    ->label('Subaccount')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active'),
                TernaryFilter::make('linked')
                    ->label('Linked to a seller')
                    ->nullable()
                    ->attribute('owner_id'),
            ])
            ->recordActions([
                ViewAction::make(),
                ConnectSellerAction::forRecord(),
                LinkSubaccountAction::make(),
            ]);
    }

    /** "Business #12", or a note that the subaccount isn't linked to anyone yet. */
    protected static function ownerLabel(Subaccount $record): ?string
    {
        if (! $record->owner) {
            return 'Not linked to a seller';
        }

        $title = $record->owner->getAttribute(FilamentPaystackConnectPlugin::get()->getSellerTitleAttribute());

        return $title !== null && $title !== $record->business_name
            ? (string) $title
            : null;
    }
}
