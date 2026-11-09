<?php

namespace App\Enums;

enum Role: string
{
    case Teacher = 'teacher';
    case Assistant = 'assistant';
    case Student = 'student';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'المدرس',
            self::Assistant => 'مساعد',
            self::Student => 'طالب',
            self::Parent => 'ولي أمر',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::Teacher, self::Assistant], true);
    }
}
