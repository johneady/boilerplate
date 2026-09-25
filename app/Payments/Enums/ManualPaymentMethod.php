<?php

namespace App\Payments\Enums;

/**
 * How money recorded by hand was received.
 */
enum ManualPaymentMethod: string
{
    case ETransfer = 'e_transfer';

    case Cheque = 'cheque';

    case Cash = 'cash';

    case PayPalMe = 'paypal_me';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ETransfer => 'Interac e-Transfer',
            self::Cheque => 'Cheque',
            self::Cash => 'Cash',
            self::PayPalMe => 'PayPal.Me transfer',
            self::Other => 'Other',
        };
    }

    /**
     * Every case keyed by value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $method) {
            $options[$method->value] = $method->label();
        }

        return $options;
    }
}
