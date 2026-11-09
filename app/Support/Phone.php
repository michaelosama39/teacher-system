<?php

namespace App\Support;

/**
 * Egyptian mobile phone normalization.
 *
 * Every phone is stored and looked up in the local format 01XXXXXXXXX.
 * Accepts +20 / 0020 / 20 country prefixes, a missing leading zero,
 * spaces, dashes, dots, parentheses and Arabic-Indic (or Persian) digits.
 */
final class Phone
{
    public const PATTERN = '/^01[0125][0-9]{8}$/';

    private const DIGIT_MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Normalize a phone to 01XXXXXXXXX (best effort; check with isValid()).
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', strtr(trim($phone), self::DIGIT_MAP)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== null && preg_match(self::PATTERN, $normalized) === 1;
    }

    /**
     * Return the normalized phone only when it is a valid Egyptian mobile.
     */
    public static function normalizeOrNull(?string $phone): ?string
    {
        return self::isValid($phone) ? self::normalize($phone) : null;
    }
}
