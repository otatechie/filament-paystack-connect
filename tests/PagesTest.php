<?php

use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentResource;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\SellerResource;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;
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
