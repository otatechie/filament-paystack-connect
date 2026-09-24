<?php

namespace Otatechie\FilamentPaystackConnect;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPaystackConnectServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-paystack-connect';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name);
    }
}
