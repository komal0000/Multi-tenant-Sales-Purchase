<?php

namespace App\Helpers;

class DateHelper
{
    const START_ENGLISH_DATE = '1943-04-14';
    const START_NEPALI_DATE = '2000-01-01';
    const MIN_YEAR_BS = 2000;
    const MAX_YEAR_BS = 2090;
    public static $_bs = [
        "2000" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2001" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2002" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2003" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2004" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2005" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2006" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2007" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2008" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        "2009" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2010" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2011" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2012" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        "2013" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2014" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2015" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2016" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        "2017" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2018" => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2019" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2020" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2021" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2022" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        "2023" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2024" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2025" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2026" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2027" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2028" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2029" => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        "2030" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2031" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2032" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2033" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2034" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2035" => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        "2036" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2037" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2038" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2039" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        "2040" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2041" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2042" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2043" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        "2044" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2045" => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2046" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2047" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2048" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2049" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        "2050" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2051" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2052" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2053" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        "2054" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2055" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2056" => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        "2057" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2058" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2059" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2060" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2061" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2062" => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        "2063" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2064" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2065" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2066" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        "2067" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2068" => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2069" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2070" => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        "2071" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2072" => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2073" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        "2074" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2075" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2076" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        "2077" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2078" => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        "2079" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2080" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        "2081" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        "2082" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        "2083" => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        "2084" => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        "2085" => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        "2086" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2087" => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        "2088" => [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        "2089" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2090" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2091" => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        "2092" => [30, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2093" => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2094" => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        "2095" => [31, 31, 32, 31, 31, 31, 30, 29, 30, 30, 30, 30],
        "2096" => [30, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        "2097" => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        "2098" => [31, 31, 32, 31, 31, 31, 29, 30, 29, 30, 29, 31],
        "2099" => [31, 31, 32, 31, 31, 31, 30, 29, 29, 30, 30, 30],
    ];

    public static function getDatesForMonth($year, $month)
    {
        $dates = [];
        $month = (int) $month;
        if ($month < 1 || $month > 12) {
            return [];
        }

        $monthArray = self::getBsYearMonths((int) $year);
        if (!is_array($monthArray)) {
            return [];
        }

        // monthArray is zero-indexed (0..11)
        $daysInMonth = $monthArray[$month - 1];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $dates[] = ($year * 10000) + $month * 100 + $i;
        }
        return $dates;
    }

    public static function formattedDate($year, $month, $day)
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function evaluateNepaliDate($daysElapsed, $format = true)
    {
        $currentYear = 0;
        $currentMonth = 0;
        $currentDay = 0;
        $totalD = 0;
        $flag = false;

        for ($i = self::MIN_YEAR_BS; $i <= self::MAX_YEAR_BS; $i++) {
            if ($flag) {
                break;
            }

            $monthData = self::getBsYearMonths($i);
            if (!is_array($monthData)) {
                continue;
            }

            // iterate months (1..12) but monthData is 0-indexed
            for ($j = 1; $j <= 12; $j++) {
                $mIndex = $j - 1;
                $monthDays = $monthData[$mIndex];
                $totalD += $monthDays;
                if ($daysElapsed - $totalD < 0) {
                    // day within this month
                    $currentDay = $daysElapsed - ($totalD - $monthDays) + 1;
                    $flag = true;
                    $currentYear = $i;
                    $currentMonth = $j;
                    break;
                }
            }
        }

        if ($format) {
            return self::formattedDate($currentYear, $currentMonth, $currentDay);
        } else {
            return [
                'year' => $currentYear,
                'month' => $currentMonth,
                'day' => $currentDay,
            ];
        }
    }

    public static function evaluateEnglishDate($date, $days)
    {
        $result = new \DateTime($date);
        $result->modify("+{$days} days");
        $year = (int) $result->format('Y');
        $month = (int) $result->format('m');
        $day = (int) $result->format('d');
        return self::formattedDate($year, $month, $day);
    }

    public static function adToBs($adDate)
    {
        $startDate = new \DateTime(self::START_ENGLISH_DATE);
        $today = new \DateTime($adDate);
        $interval = $startDate->diff($today);
        $daysDifference = (int) $interval->format('%a');

        // If $adDate is before START_ENGLISH_DATE, $daysDifference will be negative
        if ($today < $startDate) {
            throw new \Exception("Date Out of Range");
        }

        return self::evaluateNepaliDate($daysDifference);
    }

    public static function bsToAd($selectedDate)
    {
        // Split the date string and convert to integers
        list($year, $month, $day) = array_map('intval', explode('-', $selectedDate));

        $daysDiff = 0;
        // Sum full years from MIN_YEAR_BS up to the year before the selected year
        for ($i = self::MIN_YEAR_BS; $i < $year; $i++) {
            $monthData = self::getBsYearMonths($i);
            if (!is_array($monthData)) {
                continue;
            }
            foreach ($monthData as $md) {
                $daysDiff += $md;
            }
        }

        // For the selected year, sum months prior to the selected month
        $selectedYearMonths = self::getBsYearMonths($year);
        if (!is_array($selectedYearMonths)) {
            throw new \Exception("Date Out of Range");
        }
        for ($j = 1; $j < $month; $j++) {
            $daysDiff += $selectedYearMonths[$j - 1];
        }

        // add days (day - 1 because start date day is counted as day 0)
        $daysDiff += $day - 1;
        return self::evaluateEnglishDate(self::START_ENGLISH_DATE, $daysDiff);
    }

    /**
     * Helper to retrieve BS year month lengths supporting both new string keys
     * (e.g. '2000') and older numeric offset keys.
     * Returns array of 12 integers or null if not found.
     */
    private static function getBsYearMonths(int $year)
    {
        // Prefer explicit year string key if present
        $key = (string) $year;
        if (isset(self::$_bs[$key]) && is_array(self::$_bs[$key])) {
            return self::$_bs[$key];
        }

        // Fallback: support legacy numeric indexing where entries were stored with
        // zero-based offsets from MIN_YEAR_BS (e.g. 0 => 2000)
        $index = $year - self::MIN_YEAR_BS;
        if (isset(self::$_bs[$index]) && is_array(self::$_bs[$index])) {
            return self::$_bs[$index];
        }

        return null;
    }

    public static function getCurrentBS()
    {
        $today = date('Y-m-d');
        return self::adToBs($today);
    }

    public static function toDateInt(string $date): int
    {
        return (int) str_replace('-', '', $date);
    }

    public static function fromDateInt(int $date): string
    {
        $year = intdiv($date, 10000);
        $month = intdiv($date % 10000, 100);
        $day = $date % 100;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function getDaysInMonth(int $year, int $month)
    {
        if ($month < 1 || $month > 12) {
            return null;
        }

        $months = self::getBsYearMonths($year);
        if (!is_array($months)) {
            return null;
        }

        return $months[$month - 1] ?? null;
    }
}
