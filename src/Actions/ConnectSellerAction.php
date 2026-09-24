<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Banks;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Subaccount;
use Otatechie\PaystackConnect\Support\SettlementAccount;
use Throwable;

/**
 * Connects a seller's bank or mobile money account, or changes an existing
 * seller's account. Connecting the same seller again updates their subaccount.
 */
class ConnectSellerAction
{
    /** Header action: pick any seller. */
    public static function make(): Action
    {
        return static::base('connect')
            ->label('Connect seller')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => FilamentPaystackConnectPlugin::allows('create', Subaccount::class));
    }

    /** Row action: change the account of the seller on this row. */
    public static function forRecord(): Action
    {
        return static::base('updateAccount')
            ->label('Change account')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize(fn (Subaccount $record): bool => FilamentPaystackConnectPlugin::allows('update', $record))
            ->visible(fn (Subaccount $record): bool => static::plugin()->getSellerModel() !== null && $record->getAttribute('owner_id') !== null)
            ->fillForm(fn (Subaccount $record): array => [
                'seller' => $record->getAttribute('owner_id'),
                'country' => Banks::COUNTRIES[$record->currency] ?? 'ghana',
            ]);
    }

    protected static function base(string $name): Action
    {
        return Action::make($name)
            ->visible(fn (): bool => static::plugin()->getSellerModel() !== null)
            ->modalDescription('The account holder is checked with Paystack where Paystack allows it (Ghana and Nigeria).')
            ->schema([
                Select::make('seller')
                    ->label('Seller')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => static::sellers()
                        ->where(static::plugin()->getSellerTitleAttribute(), 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck(static::plugin()->getSellerTitleAttribute(), (new (static::plugin()->getSellerModel()))->getKeyName())
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => static::findSeller($value)?->getAttribute(static::plugin()->getSellerTitleAttribute()))
                    ->disabled(fn (?Model $record): bool => $record instanceof Subaccount)
                    ->dehydrated(),
                Select::make('country')
                    ->options(collect(Banks::COUNTRIES)->mapWithKeys(fn (string $country, string $currency) => [$country => ucwords($country)." ({$currency})"])->all())
                    ->default(Banks::COUNTRIES[strtoupper((string) config('paystack-connect.currency', 'GHS'))] ?? 'ghana')
                    ->required()
                    ->live(),
                Select::make('bank_code')
                    ->label('Bank or mobile money network')
                    ->options(fn (Get $get): array => static::bankOptions((string) $get('country')))
                    ->searchable()
                    ->required(),
                TextInput::make('account_number')
                    ->label('Account or wallet number')
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $seller = static::findSeller($data['seller']);
                $bank = PaystackConnect::banks()->find($data['country'], $data['bank_code']);
                $currency = array_search($data['country'], Banks::COUNTRIES, true);

                if (! $seller || ! $bank || ! $currency) {
                    Notification::make()->title('Pick a seller, and a bank from the list.')->danger()->send();
                    $action->halt();

                    return;
                }

                $name = (string) $seller->getAttribute(static::plugin()->getSellerTitleAttribute());

                $account = $bank['type'] === 'mobile_money'
                    ? SettlementAccount::mobileMoney($name, $bank['code'], $data['account_number'], $currency, $bank['name'])
                    : SettlementAccount::bank($name, $bank['code'], $data['account_number'], $currency, $bank['name']);

                try {
                    $subaccount = PaystackConnect::subaccounts()->connect($seller, $account);
                } catch (PaystackException|InvalidArgumentException $e) {
                    Notification::make()->title('Paystack refused the account')->body($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title("{$name} is connected")
                    ->body(trim("{$subaccount->bank_name} {$subaccount->maskedAccountNumber()} {$subaccount->account_name}"))
                    ->success()
                    ->send();
            });
    }

    /** @return array<string, string> */
    protected static function bankOptions(string $country): array
    {
        if ($country === '') {
            return [];
        }

        try {
            return PaystackConnect::banks()->list($country)
                ->sortBy('name')
                ->mapWithKeys(fn (array $bank): array => [$bank['code'] => $bank['name'].($bank['type'] === 'mobile_money' ? ' (mobile money)' : '')])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return Builder<Model> */
    protected static function sellers()
    {
        /** @var class-string<Model> $model */
        $model = static::plugin()->getSellerModel();

        return $model::query();
    }

    protected static function findSeller(mixed $key): ?Model
    {
        return $key === null ? null : static::sellers()->find($key);
    }

    protected static function plugin(): FilamentPaystackConnectPlugin
    {
        return FilamentPaystackConnectPlugin::get();
    }
}
