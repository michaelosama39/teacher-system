<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'في انتظار الموافقة',
            self::Active => 'نشط',
            self::Suspended => 'موقوف',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
