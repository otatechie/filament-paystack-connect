# Filament Paystack Connect

A [Filament](https://filamentphp.com) admin panel for
[otatechie/laravel-paystack-connect](https://github.com/otatechie/laravel-paystack-connect):
see every payment and its split, refund and verify payments, and set
where each seller gets paid (bank or mobile money), without leaving your panel.

It shows what Paystack's own dashboard can't: which of your sellers each
payment went to, your fee on it, and refunds the package is still holding.

## Requirements

- PHP 8.3+, Laravel 12 or 13, Filament 5.8.3 or later
- [otatechie/laravel-paystack-connect](https://github.com/otatechie/laravel-paystack-connect)
  1.2 or later, installed and set up (keys, migration and webhook)

## Installation

```bash
composer require otatechie/filament-paystack-connect
```

Register the plugin in your panel provider, and tell it which model is your
seller:

```php
use Otatechie\FilamentPaystackConnect\FilamentPaystackConnectPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            FilamentPaystackConnectPlugin::make()
                ->sellerModel(Business::class, 'name'),   // the model that gets paid, and its name column
        );
}
```

The pages appear under a **Paystack** group in the navigation.

## What you get

### Payments

- **Table:** reference, status, seller, customer, amount, your fee, refunds and
  date. Filter by status or seller, and search by reference or customer email.
- **Details:** the payment, what it was for, and the split: total, your fee,
  the seller's share, Paystack's fee, refunded and pending refunds.
- **Verify** (for payments not yet settled): asks Paystack for the latest
  status, as your callback page would.
- **Refund** (for paid payments): the whole amount left, or part of it. The
  amount is held as pending until Paystack confirms the refund by webhook.

### Sellers

- **Table:** each seller's subaccount with bank or network, a masked account
  number (`•••• 4567`), the holder's name, whether it's active, and how many
  payments it has received. The full account number is never shown.
- **Add seller:** one form with the seller's details and the account Paystack
  sends their share of every payment to: a country, a bank or mobile money
  network from Paystack's live list, and the account number. In Ghana and
  Nigeria, Paystack checks who owns the account first. If Paystack refuses
  the account, the seller isn't saved either. The seller's fields come from
  `sellerForm()` (see Options); without it, you pick one of your existing
  sellers instead.
- **Edit:** the Add seller form, filled in, to fix the seller's details or
  where they get paid. The account number isn't shown; leave it blank to keep
  the current one. It stays the same subaccount.
- **Link to seller:** attach a subaccount you imported with
  `paystack-connect:import-subaccounts` to one of your sellers.

The add, edit and link actions need `sellerModel()`. Without it, the
pages still list and show everything.

### On your own seller pages

Add **Set up payouts** to your seller's page, so you set up where a seller
gets paid from their own page, without picking them from a list:

```php
use Otatechie\FilamentPaystackConnect\Actions\ConnectPaystackAccountAction;

// app/Filament/Resources/Businesses/Pages/ViewBusiness.php (or EditBusiness)
protected function getHeaderActions(): array
{
    return [
        ConnectPaystackAccountAction::make(),
        EditAction::make(),
    ];
}
```

It reads **Edit payout account** once it's set up, and updates the same
subaccount. The seller model needs the core package's
`HasPaystackSubaccount` trait.

## Options

```php
FilamentPaystackConnectPlugin::make()
    ->sellerModel(Business::class, 'name')   // enables adding sellers
    ->sellerForm(fn () => [                  // the seller's fields in Add seller
        TextInput::make('name')->label('Business name')->required(),
    ])
    ->navigationGroup('Payments');           // default "Paystack"; null for no group
```

`sellerForm()` takes the fields your seller needs, since only your app knows
them. They're saved with `Business::create()`, so make them fillable. Leave it
out if sellers sign up through your app: Add seller then picks one of them.

## Who can see it

Everyone who can use your panel can see these pages and act on them,
including refunds. To limit that, register Laravel policies for the package's
models:

```php
use Otatechie\PaystackConnect\Models\Payment;
use Otatechie\PaystackConnect\Models\Subaccount;

Gate::policy(Payment::class, PaymentPolicy::class);
Gate::policy(Subaccount::class, SubaccountPolicy::class);
```

Filament uses `viewAny` and `view` for the pages. The actions check these
policy methods when you define them:

| Action | Policy method |
|---|---|
| Refund | `PaymentPolicy::refund(User $user, Payment $payment)` |
| Verify | `PaymentPolicy::verify(User $user, Payment $payment)` |
| Add seller, Set up payouts | `SubaccountPolicy::create(User $user)` |
| Edit, Edit payout account, Link to seller | `SubaccountPolicy::update(User $user, Subaccount $subaccount)` |

A method you don't define doesn't restrict anything, so you can limit just
refunds, for example.

## Testing

```bash
composer test
composer analyse
composer format
```

The tests use the core package's `PaystackConnect::fake()`, so nothing is sent
to Paystack.

## Security

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
