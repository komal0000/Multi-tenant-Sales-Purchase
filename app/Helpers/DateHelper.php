<?php

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OutOfRangeException;

class DateHelper
{
    public const START_ENGLISH_DATE = '1943-04-14';

    public const START_NEPALI_DATE = '2000-01-01';

    public const MIN_YEAR_BS = 2000;

    public const MAX_YEAR_BS = 2099;

    public static array $_bs = [
        '2000' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2001' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2002' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2003' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2004' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2005' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2006' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2007' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2008' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        '2009' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2010' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2011' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2012' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        '2013' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2014' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2015' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2016' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        '2017' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2018' => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2019' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2020' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2021' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2022' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        '2023' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2024' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2025' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2026' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2027' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2028' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2029' => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        '2030' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2031' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2032' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2033' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2034' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2035' => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        '2036' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2037' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2038' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2039' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        '2040' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2041' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2042' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2043' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        '2044' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2045' => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2046' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2047' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2048' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2049' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        '2050' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2051' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2052' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2053' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        '2054' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2055' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2056' => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        '2057' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2058' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2059' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2060' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2061' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2062' => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        '2063' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2064' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2065' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2066' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        '2067' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2068' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2069' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2070' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        '2071' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2072' => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2073' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        '2074' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2075' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2076' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        '2077' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2078' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        '2079' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2080' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        '2081' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        '2082' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        '2083' => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        '2084' => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        '2085' => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        '2086' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2087' => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        '2088' => [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        '2089' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2090' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2091' => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        '2092' => [30, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2093' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2094' => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        '2095' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 30, 30, 30],
        '2096' => [30, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        '2097' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        '2098' => [31, 31, 32, 31, 31, 31, 29, 30, 29, 30, 29, 31],
        '2099' => [31, 31, 32, 31, 31, 31, 30, 29, 29, 30, 30, 30],
    ];

    public static function extractDateParts(string|int $date): array
    {
        $date = preg_replace('/[^0-9]/', '', (string) $date);

        if (strlen($date) !== 8) {
            throw new InvalidArgumentException('Date must contain 8 digits.');
        }

        return [
            'year' => (int) substr($date, 0, 4),
            'month' => (int) substr($date, 4, 2),
            'day' => (int) substr($date, 6, 2),
        ];
    }

    public static function adToBs(string|DateTimeInterface $adDate): string
    {
        $adDate = self::normalizeAdDate($adDate);
        $startDate = new DateTimeImmutable(self::START_ENGLISH_DATE);
        $targetDate = new DateTimeImmutable($adDate);

        if ($targetDate < $startDate) {
            throw new OutOfRangeException('AD date is out of supported BS conversion range.');
        }

        $daysDifference = (int) $startDate->diff($targetDate)->format('%a');

        return self::evaluateNepaliDate($daysDifference);
    }

    public static function bsToAd(string|int $bsDate): string
    {
        $bsDate = is_int($bsDate) ? self::fromDateInt($bsDate) : self::normalizeBsDate($bsDate);
        ['year' => $year, 'month' => $month, 'day' => $day] = self::extractDateParts($bsDate);
        self::assertValidBsDate($year, $month, $day);

        $daysDiff = 0;
        for ($i = self::MIN_YEAR_BS; $i < $year; $i++) {
            foreach (self::getBsYearMonths($i) as $monthDays) {
                $daysDiff += $monthDays;
            }
        }

        $selectedYearMonths = self::getBsYearMonths($year);
        for ($j = 1; $j < $month; $j++) {
            $daysDiff += $selectedYearMonths[$j - 1];
        }

        $daysDiff += $day - 1;

        return self::evaluateEnglishDate(self::START_ENGLISH_DATE, $daysDiff);
    }

    public static function getCurrentBS(): string
    {
        return self::adToBs(date('Y-m-d'));
    }

    public static function toDateInt(string $date): int
    {
        $date = self::normalizeDateString($date);

        return (int) str_replace('-', '', $date);
    }

    public static function fromDateInt(int|string $date): string
    {
        $parts = self::extractDateParts((string) $date);

        return self::formattedDate($parts['year'], $parts['month'], $parts['day']);
    }

    public static function getDaysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }

        $months = self::getBsYearMonths($year);
        if ($months === null) {
            throw new OutOfRangeException('BS year is out of supported range.');
        }

        return $months[$month - 1];
    }

    public static function getDatesForMonth(int $year, int $month): array
    {
        $daysInMonth = self::getDaysInMonth($year, $month);
        $dates = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dates[] = self::toDateInt(self::formattedDate($year, $month, $day));
        }

        return $dates;
    }

    public static function getSupportedYears(): array
    {
        return range(self::MIN_YEAR_BS, self::MAX_YEAR_BS);
    }

    public static function getMonthOptions(): array
    {
        return [
            1 => 'Baisakh',
            2 => 'Jestha',
            3 => 'Ashadh',
            4 => 'Shrawan',
            5 => 'Bhadra',
            6 => 'Ashwin',
            7 => 'Kartik',
            8 => 'Mangsir',
            9 => 'Poush',
            10 => 'Magh',
            11 => 'Falgun',
            12 => 'Chaitra',
        ];
    }

    public static function getBsMonthMap(): array
    {
        return self::$_bs;
    }

    public static function adToBsInt(string|DateTimeInterface $adDate): int
    {
        return self::toDateInt(self::adToBs($adDate));
    }

    public static function currentBsInt(): int
    {
        return self::toDateInt(self::getCurrentBS());
    }

    public static function bsIntToAd(int $dateInt): string
    {
        return self::bsToAd(self::fromDateInt($dateInt));
    }

    public static function getAdRangeFromBsFilters(?string $fromBsDate, ?string $toBsDate): array
    {
        $fromAd = $fromBsDate ? self::bsToAd($fromBsDate) : null;
        $toAd = $toBsDate ? self::bsToAd($toBsDate) : null;

        if ($fromAd && $toAd && $fromAd > $toAd) {
            throw new InvalidArgumentException('From BS date must be before or equal to To BS date.');
        }

        return [$fromAd, $toAd];
    }

    public static function getBsIntRangeFromFilters(?string $fromBsDate, ?string $toBsDate): array
    {
        $fromBsInt = $fromBsDate ? self::toDateInt(self::normalizeBsDate($fromBsDate)) : null;
        $toBsInt = $toBsDate ? self::toDateInt(self::normalizeBsDate($toBsDate)) : null;

        if ($fromBsInt && $toBsInt && $fromBsInt > $toBsInt) {
            throw new InvalidArgumentException('From BS date must be before or equal to To BS date.');
        }

        return [$fromBsInt, $toBsInt];
    }

    public static function getAdRangeForBsMonth(int $year, int $month): array
    {
        $startBs = self::formattedDate($year, $month, 1);
        $endBs = self::formattedDate($year, $month, self::getDaysInMonth($year, $month));

        return [
            'bs_start' => $startBs,
            'bs_end' => $endBs,
            'ad_start' => self::bsToAd($startBs),
            'ad_end' => self::bsToAd($endBs),
        ];
    }

    public static function formattedDate(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function evaluateNepaliDate(int $daysElapsed, bool $format = true): string|array
    {
        $currentYear = 0;
        $currentMonth = 0;
        $currentDay = 0;
        $totalDays = 0;
        $found = false;

        for ($year = self::MIN_YEAR_BS; $year <= self::MAX_YEAR_BS; $year++) {
            if ($found) {
                break;
            }

            $monthData = self::getBsYearMonths($year);
            if ($monthData === null) {
                continue;
            }

            for ($month = 1; $month <= 12; $month++) {
                $monthDays = $monthData[$month - 1];
                $totalDays += $monthDays;

                if ($daysElapsed - $totalDays < 0) {
                    $currentDay = $daysElapsed - ($totalDays - $monthDays) + 1;
                    $currentYear = $year;
                    $currentMonth = $month;
                    $found = true;
                    break;
                }
            }
        }

        if (! $found) {
            throw new OutOfRangeException('AD date is out of supported BS conversion range.');
        }

        return $format
            ? self::formattedDate($currentYear, $currentMonth, $currentDay)
            : ['year' => $currentYear, 'month' => $currentMonth, 'day' => $currentDay];
    }

    public static function evaluateEnglishDate(string $date, int $days): string
    {
        $result = new DateTimeImmutable($date);
        $result = $result->modify("+{$days} days");

        return self::formattedDate((int) $result->format('Y'), (int) $result->format('m'), (int) $result->format('d'));
    }

    public static function normalizeAdDate(string|DateTimeInterface $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $date = trim($date);

        if ($date === '') {
            throw new InvalidArgumentException('Date must be in YYYY-MM-DD format.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $normalized = self::normalizeDateString($date);
            new DateTimeImmutable($normalized);

            return $normalized;
        }

        try {
            return (new DateTimeImmutable($date))->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Date must be in YYYY-MM-DD format.');
        }
    }

    public static function normalizeBsDate(string $date): string
    {
        $date = self::normalizeDateString($date);
        ['year' => $year, 'month' => $month, 'day' => $day] = self::extractDateParts($date);
        self::assertValidBsDate($year, $month, $day);

        return self::formattedDate($year, $month, $day);
    }

    public static function normalizeDateString(string $date): string
    {
        $date = trim($date);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Date must be in YYYY-MM-DD format.');
        }

        return $date;
    }

    public static function assertValidBsDate(int $year, int $month, int $day): void
    {
        if ($year < self::MIN_YEAR_BS || $year > self::MAX_YEAR_BS) {
            throw new OutOfRangeException('BS year is out of supported range.');
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('BS month must be between 1 and 12.');
        }

        $daysInMonth = self::getDaysInMonth($year, $month);
        if ($day < 1 || $day > $daysInMonth) {
            throw new InvalidArgumentException('BS day is invalid for the selected month.');
        }
    }

    public static function getBsYearMonths(int $year): ?array
    {
        $key = (string) $year;
        if (isset(self::$_bs[$key]) && is_array(self::$_bs[$key])) {
            return self::$_bs[$key];
        }

        $index = $year - self::MIN_YEAR_BS;
        if (isset(self::$_bs[$index]) && is_array(self::$_bs[$index])) {
            return self::$_bs[$index];
        }

        return null;
    }
}
