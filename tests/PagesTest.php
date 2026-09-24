<?php

use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Otatechie\FilamentPaystackConnect\Actions\ConnectPaystackAccountAction;
use Otatechie\FilamentPaystackConnect\Actions\ConnectSellerAction;
use Otatechie\FilamentPaystackConnect\Actions\LinkSubaccountAction;
use Otatechie\FilamentPaystackConnect\Actions\RefundPaymentAction;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentResource;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\SellerResource;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;
use Otatechie\FilamentPaystackConnect\View\PaystackConnectIconAlias;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Support\SettlementAccount;

it('renders every page in the panel, under the Paystack group', function () {
    $paystack = PaystackConnect::fake();
    $seller = Business::create(['name' => 'Kofi Prints']);
    $subaccount = $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Kofi Prints', 'MTN', '0241234567', 'GHS'));
    $payment = $paystack->pay(PaystackConnect::checkout()->amount('50')->email('c@example.com')->seller($seller)->create());

    $this->get(PaymentResource::getUrl('index'))->assertOk()->assertSee('Paystack')->assertSee('Payments')->assertSee('Sellers');
    $this->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertOk()->assertSee($payment->reference);
    $this->get(SellerResource::getUrl('index'))->assertOk()->assertSee('Add seller');
    $this->get(SellerResource::getUrl('view', ['record' => $subaccount]))->assertOk()->assertSee('•••• 4567');

    expect(PaymentResource::getUrl('index'))->toEndWith('/admin/paystack/payments');
});

it('uses Heroicons in the sidebar unless a theme registers its own', function () {
    expect(PaymentResource::getNavigationIcon())->toBe(Heroicon::OutlinedBanknotes)
        ->and(SellerResource::getNavigationIcon())->toBe(Heroicon::OutlinedBuildingStorefront);

    FilamentIcon::register([
        PaystackConnectIconAlias::PAYMENTS_NAVIGATION_ITEM => 'lucide-banknote',
        PaystackConnectIconAlias::SELLERS_NAVIGATION_ITEM => 'lucide-store',
    ]);

    expect(PaymentResource::getNavigationIcon())->toBe('lucide-banknote')
        ->and(SellerResource::getNavigationIcon())->toBe('lucide-store');
});

it('sizes each modal to its form instead of Filament\'s 4xl default', function () {
    expect(ConnectSellerAction::make()->getModalWidth())->toBe(Width::Large)
        ->and(ConnectSellerAction::forRecord()->getModalWidth())->toBe(Width::Large)
        ->and(ConnectPaystackAccountAction::make()->getModalWidth())->toBe(Width::Large)
        ->and(RefundPaymentAction::make()->getModalWidth())->toBe(Width::Medium)
        ->and(LinkSubaccountAction::make()->getModalWidth())->toBe(Width::Medium);
});
