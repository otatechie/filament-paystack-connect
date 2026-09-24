<?php

namespace Otatechie\FilamentPaystackConnect;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Otatechie\FilamentPaystackConnect\Resources\Payments\PaymentResource;
use Otatechie\FilamentPaystackConnect\Resources\Sellers\SellerResource;

/**
 * Adds Paystack payments and sellers to a Filament panel:
 *
 *     $panel->plugin(
 *         FilamentPaystackConnectPlugin::make()->sellerModel(Business::class, 'name'),
 *     );
 */
class FilamentPaystackConnectPlugin implements Plugin
{
    /** @var class-string<Model>|null */
    protected ?string $sellerModel = null;

    protected string $sellerTitleAttribute = 'name';

    protected ?string $navigationGroup = 'Paystack';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'paystack-connect';
    }

    /**
     * The model that gets paid, such as a business or vendor. Enables connecting
     * sellers from the panel; $titleAttribute is shown to pick one.
     *
     * @param  class-string<Model>  $model
     */
    public function sellerModel(string $model, string $titleAttribute = 'name'): static
    {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("{$model} is not an Eloquent model.");
        }

        $this->sellerModel = $model;
        $this->sellerTitleAttribute = $titleAttribute;

        return $this;
    }

    /** @return class-string<Model>|null */
    public function getSellerModel(): ?string
    {
        return $this->sellerModel;
    }

    public function getSellerTitleAttribute(): string
    {
        return $this->sellerTitleAttribute;
    }

    /** Null puts the pages at the top level of the navigation. */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    /**
     * Whether the current user may do $ability to $model. Apps without a policy
     * for the model, or without that method on it, aren't restricted.
     *
     * @param  Model|class-string<Model>  $model
     */
    public static function allows(string $ability, Model|string $model): bool
    {
        $policy = Gate::getPolicyFor($model);

        if ($policy === null || ! method_exists($policy, $ability)) {
            return true;
        }

        return Gate::allows($ability, $model);
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            PaymentResource::class,
            SellerResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
