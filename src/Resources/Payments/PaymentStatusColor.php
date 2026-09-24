<?php

namespace Otatechie\FilamentPaystackConnect\Resources\Payments;

use Otatechie\PaystackConnect\Enums\PaymentStatus;

/** Badge colour and label for each payment status. */
final class PaymentStatusColor
{
    public static function color(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Success => 'success',
            PaymentStatus::Failed => 'danger',
            PaymentStatus::AmountMismatch => 'warning',
            PaymentStatus::Refunded => 'info',
            PaymentStatus::Pending => 'gray',
        };
    }

    public static function label(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::AmountMismatch => 'Amount mismatch',
            default => ucfirst($status->value),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => self::label($status)])->all();
    }
}
