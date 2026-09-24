<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Sellers;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ListSellers;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ViewSeller;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Schemas\SellerInfolist;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Tables\SellersTable;
use Otatechie\FilamentPaystackConnect\View\PaystackConnectIconAlias;
use Otatechie\PaystackConnect\Models\Subaccount;
use UnitEnum;

/** Sellers' Paystack subaccounts. Connecting and changing accounts goes through Paystack, so there's no plain edit form. */
class SellerResource extends Resource
{
    protected static ?string $model = Subaccount::class;

    protected static ?string $slug = 'paystack/sellers';

    protected static ?string $modelLabel = 'seller';

    protected static ?string $recordTitleAttribute = 'business_name';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::$navigationIcon
            ?? FilamentIcon::resolve(PaystackConnectIconAlias::SELLERS_NAVIGATION_ITEM)
            ?? Heroicon::OutlinedBuildingStorefront;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentPaystackConnectPlugin::get()->getNavigationGroup();
    }

    public static function infolist(Schema $schema): Schema
    {
        return SellerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellers::route('/'),
            'view' => ViewSeller::route('/{record}'),
        ];
    }
}
