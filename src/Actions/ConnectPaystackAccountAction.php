<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Banks;
use Otatechie\PaystackConnect\Models\Subaccount;

/**
 * For your own seller pages: sets up where this seller gets paid, or changes
 * it once set up.
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [ConnectPaystackAccountAction::make()];
 *     }
 */
class ConnectPaystackAccountAction
{
    public static function make(): Action
    {
        return Action::make('connectPaystack')
            ->label(fn (Model $record): string => static::subaccount($record) ? 'Edit payout account' : 'Set up payouts')
            ->icon(Heroicon::OutlinedBuildingLibrary)
            ->authorize(fn (Model $record): bool => ($subaccount = static::subaccount($record))
                ? FilamentPaystackConnectPlugin::allows('update', $subaccount)
                : FilamentPaystackConnectPlugin::allows('create', Subaccount::class))
            ->modalDescription(ConnectSellerAction::DESCRIPTION)
            ->fillForm(fn (Model $record): array => array_filter([
                'country' => Banks::COUNTRIES[static::subaccount($record)->currency ?? ''] ?? null,
            ]))
            ->schema(ConnectSellerAction::accountFields())
            ->action(fn (Model $record, array $data, Action $action) => ConnectSellerAction::connect($record, $data, $action));
    }

    protected static function subaccount(Model $seller): ?Subaccount
    {
        return Subaccount::query()
            ->where('owner_type', $seller->getMorphClass())
            ->where('owner_id', $seller->getKey())
            ->first();
    }
}
