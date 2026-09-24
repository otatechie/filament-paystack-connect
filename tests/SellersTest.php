<?php

use Filament\Actions\Testing\TestAction;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ListSellers;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ViewSeller;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Subaccount;
use Otatechie\PaystackConnect\Support\SettlementAccount;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->paystack = PaystackConnect::fake();
});

it('connects a seller\'s mobile money wallet from the panel', function () {
    $seller = Business::create(['name' => 'Ama Foods']);

    livewire(ListSellers::class)
        ->callAction('connect', [
            'seller' => $seller->id,
            'country' => 'ghana',
            'bank_code' => 'MTN',
            'account_number' => '0551234987',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Ama Foods is connected');

    $this->paystack->assertSubaccountCreated(fn ($data) => $data['settlement_bank'] === 'MTN' && $data['business_name'] === 'Ama Foods');
    expect($seller->paystackSubaccount->maskedAccountNumber())->toBe('•••• 4987');
});

it('lists sellers with a masked account and never the full number', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Ama Foods', 'MTN', '0551234987', 'GHS'));

    livewire(ListSellers::class)
        ->assertCanSeeTableRecords([$seller->paystackSubaccount])
        ->assertSee('•••• 4987')
        ->assertDontSee('0551234987');
});

it('changes a connected seller\'s account instead of creating a second one', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $subaccount = $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Ama Foods', 'MTN', '0551234987', 'GHS'));

    livewire(ViewSeller::class, ['record' => $subaccount->getKey()])
        ->callAction('updateAccount', ['country' => 'ghana', 'bank_code' => 'VOD', 'account_number' => '0201112222'])
        ->assertNotified('Ama Foods is connected');

    expect(Subaccount::count())->toBe(1)
        ->and($subaccount->refresh()->account_number_last4)->toBe('2222');
});

it('links an imported subaccount to a seller', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $imported = Subaccount::create([
        'subaccount_code' => 'ACCT_old', 'business_name' => 'Ama Foods', 'account_number' => '1',
        'account_number_last4' => '1', 'currency' => 'GHS',
    ]);

    livewire(ListSellers::class)
        ->assertActionVisible(TestAction::make('link')->table($imported))
        ->callAction(TestAction::make('link')->table($imported), ['seller' => $seller->id])
        ->assertNotified('Subaccount linked');

    expect($imported->refresh()->owner->is($seller))->toBeTrue();
});
