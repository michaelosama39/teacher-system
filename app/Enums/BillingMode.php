<?php

namespace App\Enums;

enum BillingMode: string
{
    case Monthly = 'monthly';
    case PerSession = 'per_session';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'اشتراك شهري',
            self::PerSession => 'بالحصة',
        };
    }

    public static function current(): self
    {
        return self::tryFrom((string) config('teacher.billing_mode')) ?? self::Monthly;
    }
}
