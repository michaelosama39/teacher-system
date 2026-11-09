<?php

namespace App\Support;

/**
 * Generates easy-to-dictate temporary passwords (no ambiguous characters like 0/o, 1/l).
 */
final class TempPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(int $length = 10): string
    {
        $password = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
