<?php

namespace App\Enums;

enum ParentRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'الأب',
            self::Mother => 'الأم',
            self::Guardian => 'ولي الأمر',
        };
    }
}
