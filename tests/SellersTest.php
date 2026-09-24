<?php

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ListSellers;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\Pages\ViewSeller;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Resources\ViewBusiness;
use Otatechie\PaystackConnect\Exceptions\PaystackException;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Http\PaystackClient;
use Otatechie\PaystackConnect\Models\Subaccount;
use Otatechie\PaystackConnect\PaymentReconciler;
use Otatechie\PaystackConnect\Subaccounts;
use Otatechie\PaystackConnect\Support\SettlementAccount;
use Otatechie\PaystackConnect\Testing\PaystackFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->paystack = PaystackConnect::fake();
});

it('adds a seller and where they get paid in one form', function () {
    livewire(ListSellers::class)
        ->callAction('connect', [
            'new_seller' => ['name' => 'Tema Crafts'],
            'country' => 'ghana',
            'bank_code' => 'MTN',
            'account_number' => '0551234987',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Tema Crafts will be paid to MTN •••• 4987');

    $this->paystack->assertSubaccountCreated(fn ($data) => $data['settlement_bank'] === 'MTN' && $data['business_name'] === 'Tema Crafts');
    expect(Business::sole()->name)->toBe('Tema Crafts')
        ->and(Business::sole()->paystackSubaccount->maskedAccountNumber())->toBe('•••• 4987');
});

it('does not keep the new seller when Paystack refuses the account', function () {
    $refusing = new class(app(PaymentReconciler::class)) extends PaystackFake
    {
        public function post(string $uri, array $data = []): array
        {
            $e = new PaystackException('Paystack POST /subaccount failed (400): Account number is invalid');
            $e->body = ['status' => false, 'message' => 'Account number is invalid'];

            throw $e;
        }
    };
    app()->instance(PaystackClient::class, $refusing);
    app()->forgetInstance(Subaccounts::class);

    livewire(ListSellers::class)
        ->callAction('connect', [
            'new_seller' => ['name' => 'Tema Crafts'],
            'country' => 'ghana',
            'bank_code' => 'MTN',
            'account_number' => '0551234987',
        ])
        ->assertNotified(Notification::make()->title('Paystack refused the account')->body('Account number is invalid')->danger());

    expect(Business::count())->toBe(0)
        ->and(Subaccount::count())->toBe(0);
});

it('picks an existing seller when the app has no seller form', function () {
    FilamentPaystackConnectPlugin::get()->sellerForm(null);
    Business::create(['name' => 'Kofi Prints']);
    $seller = Business::create(['name' => 'Ama Foods']);

    livewire(ListSellers::class)
        ->mountAction('connect')
        ->assertFormFieldExists('seller', 'mountedActionSchema0', fn (Select $field): bool => array_values($field->getOptions()) === ['Ama Foods', 'Kofi Prints'])
        ->fillForm(['seller' => $seller->id, 'country' => 'ghana', 'bank_code' => 'MTN', 'account_number' => '0551234987'], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Ama Foods will be paid to MTN •••• 4987');

    expect(Business::count())->toBe(2)
        ->and($seller->paystackSubaccount->maskedAccountNumber())->toBe('•••• 4987');
});

it('lists sellers with a masked account and never the full number', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Ama Foods', 'MTN', '0551234987', 'GHS'));

    livewire(ListSellers::class)
        ->assertCanSeeTableRecords([$seller->paystackSubaccount])
        ->assertSee('•••• 4987')
        ->assertDontSee('0551234987');
});

it('edits a seller\'s payout account instead of creating a second one', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $subaccount = $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Ama Foods', 'MTN', '0551234987', 'GHS'));

    livewire(ViewSeller::class, ['record' => $subaccount->getKey()])
        ->callAction('updateAccount', ['country' => 'ghana', 'bank_code' => 'VOD', 'account_number' => '0201112222'])
        ->assertNotified('Ama Foods will be paid to Telecel Cash •••• 2222');

    expect(Subaccount::count())->toBe(1)
        ->and($subaccount->refresh()->account_number_last4)->toBe('2222');
});

it('edits the seller\'s name and keeps the account when the number is left blank', function () {
    $seller = Business::create(['name' => 'Ama Foods']);
    $subaccount = $seller->connectPaystackAccount(SettlementAccount::mobileMoney('Ama Foods', 'MTN', '0551234987', 'GHS'));

    livewire(ViewSeller::class, ['record' => $subaccount->getKey()])
        ->mountAction('updateAccount')
        ->assertSchemaStateSet(['new_seller' => ['name' => 'Ama Foods'], 'bank_code' => 'MTN', 'account_number' => null], 'mountedActionSchema0')
        ->fillForm(['new_seller' => ['name' => 'Ama Foods & Drinks']], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Ama Foods & Drinks will be paid to MTN •••• 4987');

    expect($seller->refresh()->name)->toBe('Ama Foods & Drinks')
        ->and($subaccount->refresh()->business_name)->toBe('Ama Foods & Drinks')
        ->and($subaccount->account_number)->toBe('0551234987');
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

it('sets up payouts from the seller\'s own page, then changes the same account', function () {
    $seller = Business::create(['name' => 'Ama Foods']);

    livewire(ViewBusiness::class, ['record' => $seller->id])
        ->assertActionHasLabel('connectPaystack', 'Set up payouts')
        ->callAction('connectPaystack', ['country' => 'ghana', 'bank_code' => 'MTN', 'account_number' => '0551234987'])
        ->assertHasNoFormErrors()
        ->assertNotified('Ama Foods will be paid to MTN •••• 4987');

    $code = $seller->paystackSubaccount->subaccount_code;

    livewire(ViewBusiness::class, ['record' => $seller->id])
        ->assertActionHasLabel('connectPaystack', 'Edit payout account')
        ->callAction('connectPaystack', ['country' => 'ghana', 'bank_code' => 'MTN', 'account_number' => '0247654321'])
        ->assertHasNoFormErrors();

    expect(Subaccount::count())->toBe(1)
        ->and($seller->refresh()->paystackSubaccount->subaccount_code)->toBe($code)
        ->and($seller->paystackSubaccount->maskedAccountNumber())->toBe('•••• 4321');
});
