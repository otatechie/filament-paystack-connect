<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Pages\ListPayments;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Pages\ViewPayment;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Schemas\PaymentInfolist;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Tables\PaymentsTable;
use Otatechie\FilamentPaystackConnect\View\PaystackConnectIconAlias;
use Otatechie\PaystackConnect\Models\Payment;
use UnitEnum;

/** Payments are created by checkouts, so this resource only lists and shows them. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'paystack/payments';

    protected static ?string $modelLabel = 'payment';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::$navigationIcon
            ?? FilamentIcon::resolve(PaystackConnectIconAlias::PAYMENTS_NAVIGATION_ITEM)
            ?? Heroicon::OutlinedBanknotes;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentPaystackConnectPlugin::get()->getNavigationGroup();
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
