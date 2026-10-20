<?php

namespace App\Support;

class PhoneNumber
{
    public static function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?: '';
    }

    /** Canonical local form: 08xxxxxxxxxx */
    public static function normalize(string $phone): string
    {
        $digits = static::digits($phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /** @return list<string> */
    public static function variants(string $phone): array
    {
        $normalized = static::normalize($phone);
        if ($normalized === '') {
            return [];
        }

        $international = '62'.ltrim($normalized, '0');

        return array_values(array_unique([
            $normalized,
            $international,
            ltrim($normalized, '0'),
            static::digits($phone),
        ]));
    }
}
