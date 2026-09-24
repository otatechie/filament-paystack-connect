<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Pages\ListPayments;
use Otatechie\FilamentPaystackConnect\Resources\Payments\Pages\ViewPayment;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;
use Otatechie\PaystackConnect\Enums\PaymentStatus;
use Otatechie\PaystackConnect\Facades\PaystackConnect;
use Otatechie\PaystackConnect\Models\Payment;
use Otatechie\PaystackConnect\Support\SettlementAccount;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->paystack = PaystackConnect::fake();
    $this->seller = Business::create(['name' => 'Kofi Prints']);
    $this->seller->connectPaystackAccount(SettlementAccount::mobileMoney('Kofi Prints', 'MTN', '0241234567', 'GHS'));
});

function checkout(string $amount = '100.00'): Payment
{
    return PaystackConnect::checkout()->amount($amount, 'GHS')->email('customer@example.com')->seller(test()->seller)->create();
}

it('lists payments with their seller and amounts, and filters by status', function () {
    $paid = $this->paystack->pay(checkout('100.00'));
    $pending = checkout('20.00');

    livewire(ListPayments::class)
        ->assertCanSeeTableRecords([$paid, $pending])
        ->assertSee('Kofi Prints')
        ->assertSee('GHS 100.00')
        ->assertSee('GHS 2.50') // the default 2.5% fee
        ->filterTable('status', PaymentStatus::Success->value)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('shows a payment with the split', function () {
    $payment = $this->paystack->pay(checkout('100.00'));

    livewire(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertOk()
        ->assertSee($payment->reference)
        ->assertSee('GHS 97.50'); // the seller's share
});

it('verifies a pending payment with Paystack', function () {
    $payment = checkout(); // not paid yet: Paystack reports it as abandoned

    livewire(ListPayments::class)
        ->assertActionVisible(TestAction::make('verify')->table($payment))
        ->callAction(TestAction::make('verify')->table($payment))
        ->assertNotified('Paystack says: pending');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    expect(collect($this->paystack->requests())->pluck('uri'))->toContain('/transaction/verify/'.$payment->reference);
});

it('refunds part of a payment and holds it while Paystack processes it', function () {
    $payment = $this->paystack->pay(checkout('100.00'));

    livewire(ListPayments::class)
        ->callAction(TestAction::make('refund')->table($payment), ['amount' => '40'])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($payment->refresh()->pendingRefundAmount()->toMajorString())->toBe('40.00')
        ->and($payment->refundableAmount()->toMajorString())->toBe('60.00');
    expect(collect($this->paystack->requests())->firstWhere('uri', '/refund')['data']['amount'])->toBe(4000);
});

it('only offers a refund on paid payments, and verify only on unsettled ones', function () {
    $pending = checkout();
    $paid = $this->paystack->pay(checkout());

    livewire(ListPayments::class)
        ->assertActionHidden(TestAction::make('refund')->table($pending))
        ->assertActionVisible(TestAction::make('refund')->table($paid))
        ->assertActionHidden(TestAction::make('verify')->table($paid));
});

it('shows Paystack\'s reason when a refund is refused', function () {
    $payment = $this->paystack->pay(checkout('100.00'));

    livewire(ListPayments::class)
        ->callAction(TestAction::make('refund')->table($payment), ['amount' => '500'])
        ->assertNotified('The refund was not requested');

    expect($payment->refresh()->pendingRefundAmount()->isZero())->toBeTrue();
});

it('hides the refund action from users the app\'s policy denies', function () {
    Gate::policy(Payment::class, RefundsOnlyForFinance::class);
    $payment = $this->paystack->pay(checkout());

    livewire(ListPayments::class)
        ->assertActionHidden(TestAction::make('refund')->table($payment))
        ->assertActionVisible(TestAction::make('verify')->table(checkout())); // no verify() on the policy: not restricted
});

class RefundsOnlyForFinance
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, $payment): bool
    {
        return true;
    }

    public function refund($user, $payment): bool
    {
        return $user->email === 'finance@example.com';
    }
}
