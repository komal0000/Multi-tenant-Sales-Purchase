<?php

namespace App\Support;

class NepalPhone
{
    public const INPUT_PATTERN = '/^(\+977-9\d{9}|9\d{9})$/';
    public const STORAGE_PATTERN = '/^9\d{9}$/';

    public static function normalizeForStorage(null|string|int $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '977') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        }

        if (preg_match(self::STORAGE_PATTERN, $digits) === 1) {
            return $digits;
        }

        return $raw;
    }
}
