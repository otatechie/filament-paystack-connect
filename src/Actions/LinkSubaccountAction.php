<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Subaccount;

/** Links an imported subaccount, which has no seller yet, to one of your sellers. */
class LinkSubaccountAction
{
    public static function make(): Action
    {
        return Action::make('link')
            ->label('Link to seller')
            ->icon(Heroicon::OutlinedLink)
            ->authorize(fn (Subaccount $record): bool => FilamentPaystackConnectPlugin::allows('update', $record))
            ->visible(fn (Subaccount $record): bool => FilamentPaystackConnectPlugin::get()->getSellerModel() !== null && $record->getAttribute('owner_id') === null)
            ->schema([
                Select::make('seller')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => ConnectSellerAction::sellerOptions())
                    ->getSearchResultsUsing(fn (string $search): array => ConnectSellerAction::sellerOptions($search))
                    ->getOptionLabelUsing(function ($value): ?string {
                        $plugin = FilamentPaystackConnectPlugin::get();
                        /** @var class-string<Model> $model */
                        $model = $plugin->getSellerModel();

                        return $model::query()->find($value)?->getAttribute($plugin->getSellerTitleAttribute());
                    }),
            ])
            ->action(function (Subaccount $record, array $data, Action $action): void {
                /** @var class-string<Model> $model */
                $model = FilamentPaystackConnectPlugin::get()->getSellerModel();

                try {
                    PaystackConnect::subaccounts()->attach($model::query()->findOrFail($data['seller']), $record->subaccount_code);
                } catch (PaystackException $e) {
                    Notification::make()->title('Paystack refused the change')->body(FilamentPaystackConnectPlugin::errorMessage($e))->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title('Subaccount linked')->success()->send();
            });
    }
}
