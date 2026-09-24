<?php

namespace Otatechie\FilamentPaystackConnect\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\PaystackConnect\Banks;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Subaccount;
use Otatechie\PaystackConnect\Support\SettlementAccount;
use Throwable;

/**
 * Adds a seller with where they get paid, or changes where an existing seller
 * gets paid. A seller with an account already keeps the same subaccount.
 */
class ConnectSellerAction
{
    public const DESCRIPTION = 'Paystack sends this seller\'s share of every payment to this account. In Ghana and Nigeria, Paystack checks who owns it first.';

    /**
     * Header action. With sellerForm(), it creates the seller from those fields;
     * without, it picks one of your existing sellers.
     */
    public static function make(): Action
    {
        return static::base('connect')
            ->label('Add seller')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => FilamentPaystackConnectPlugin::allows('create', Subaccount::class))
            ->schema(fn (): array => [
                ...(($form = static::plugin()->getSellerForm())
                    ? [Group::make($form())->statePath('new_seller')]
                    : [static::sellerSelect()]),
                ...static::accountFields(),
            ])
            ->action(function (array $data, Action $action): void {
                if (! isset($data['new_seller'])) {
                    static::connectExisting($data, $action);

                    return;
                }

                // If Paystack refuses the account, the new seller isn't kept either.
                DB::transaction(fn () => static::connect(static::plugin()->getSellerModel()::create($data['new_seller']), $data, $action));
            });
    }

    /**
     * Row action: the Add seller form, filled in. The account number is never
     * shown; left blank, the stored one is kept.
     */
    public static function forRecord(): Action
    {
        return static::base('updateAccount')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize(fn (Subaccount $record): bool => FilamentPaystackConnectPlugin::allows('update', $record))
            ->visible(fn (Subaccount $record): bool => static::plugin()->getSellerModel() !== null && $record->getAttribute('owner_id') !== null)
            ->fillForm(fn (Subaccount $record): array => [
                'new_seller' => static::sellerState($record->owner),
                'seller' => $record->getAttribute('owner_id'),
                'country' => Banks::COUNTRIES[$record->currency] ?? 'ghana',
                'bank_code' => $record->settlement_bank,
            ])
            ->schema(fn (Subaccount $record): array => [
                ...(($form = static::plugin()->getSellerForm())
                    ? [Group::make($form())->statePath('new_seller')]
                    : [static::sellerSelect()->disabled()]),
                ...static::accountFields($record),
            ])
            ->action(function (Subaccount $record, array $data, Action $action): void {
                $seller = $record->owner;

                if (! $seller) {
                    Notification::make()->title('This subaccount has no seller.')->danger()->send();
                    $action->halt();

                    return;
                }

                $data['account_number'] = filled($data['account_number'] ?? null) ? $data['account_number'] : $record->account_number;

                // If Paystack refuses the account, the seller's changes aren't kept either.
                DB::transaction(function () use ($seller, $data, $action): void {
                    if (isset($data['new_seller'])) {
                        $seller->update($data['new_seller']);
                    }

                    static::connect($seller, $data, $action);
                });
            });
    }

    protected static function base(string $name): Action
    {
        return Action::make($name)
            ->visible(fn (): bool => static::plugin()->getSellerModel() !== null)
            ->modalDescription(self::DESCRIPTION);
    }

    /**
     * The seller's attributes for sellerForm(), without its key and timestamps.
     *
     * @return array<string, mixed>
     */
    protected static function sellerState(?Model $seller): array
    {
        return $seller
            ? Arr::except($seller->attributesToArray(), [$seller->getKeyName(), $seller->getCreatedAtColumn(), $seller->getUpdatedAtColumn()])
            : [];
    }

    protected static function sellerSelect(): Select
    {
        return Select::make('seller')
            ->label('Seller')
            ->required()
            ->searchable()
            ->options(fn (): array => static::sellerOptions())
            ->getSearchResultsUsing(fn (string $search): array => static::sellerOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => static::findSeller($value)?->getAttribute(static::plugin()->getSellerTitleAttribute()))
            ->dehydrated();
    }

    /** @param  array<string, mixed>  $data */
    protected static function connectExisting(array $data, Action $action): void
    {
        $seller = static::findSeller($data['seller'] ?? null);

        if (! $seller) {
            Notification::make()->title('Pick a seller.')->danger()->send();
            $action->halt();

            return;
        }

        static::connect($seller, $data, $action);
    }

    /**
     * Country, bank or network, and account number. With $current, the number
     * may be left blank to keep the current one.
     *
     * @return array<int, Component>
     */
    public static function accountFields(?Subaccount $current = null): array
    {
        return [
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
                ->helperText($current ? "Leave blank to keep {$current->maskedAccountNumber()}." : 'Where this seller gets paid.')
                ->required($current === null),
        ];
    }

    /**
     * Creates or updates $seller's subaccount from the account fields.
     *
     * @param  array<string, mixed>  $data
     */
    public static function connect(Model $seller, array $data, Action $action): void
    {
        $bank = PaystackConnect::banks()->find($data['country'], $data['bank_code']);
        $currency = array_search($data['country'], Banks::COUNTRIES, true);

        if (! $bank || ! $currency) {
            Notification::make()->title('Pick a bank or network from the list.')->danger()->send();
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
            Notification::make()->title('Paystack refused the account')->body(FilamentPaystackConnectPlugin::errorMessage($e))->danger()->send();
            $action->halt();

            return;
        }

        Notification::make()
            ->title(trim("{$name} will be paid to {$subaccount->bank_name} {$subaccount->maskedAccountNumber()}"))
            ->body($subaccount->account_name)
            ->success()
            ->send();
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

    /**
     * The first 50 sellers by name, or those matching the search.
     *
     * @return array<string|int, string>
     */
    public static function sellerOptions(string $search = ''): array
    {
        $title = static::plugin()->getSellerTitleAttribute();

        return static::sellers()
            ->when($search !== '', fn (Builder $query) => $query->where($title, 'like', "%{$search}%"))
            ->orderBy($title)
            ->limit(50)
            ->pluck($title, (new (static::plugin()->getSellerModel()))->getKeyName())
            ->all();
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
