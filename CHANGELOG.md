# Changelog

All notable changes to this package are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## Unreleased

First release: a Filament 5 panel for
[otatechie/laravel-paystack-connect](https://github.com/otatechie/laravel-paystack-connect).

### Added
- Payments: a table with status, seller, amount, your fee and refunds, filters by status and seller, a details page with the split, and **Verify** and **Refund** actions.
- Sellers: a table with masked accounts and holders, a **Connect seller** action using Paystack's live bank and mobile money list, **Change account**, and **Link to seller** for imported subaccounts.
- Actions respect the app's policies when defined: `refund` and `verify` on payments, `create` and `update` on subaccounts.
