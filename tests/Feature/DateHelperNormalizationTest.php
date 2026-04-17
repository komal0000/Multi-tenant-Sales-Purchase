<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class DateHelperNormalizationTest extends TestCase
{
    public function test_normalize_ad_date_accepts_datetime_string(): void
    {
        $this->assertSame('2026-04-16', DateHelper::normalizeAdDate('2026-04-16 13:24:08'));
    }

    public function test_ad_to_bs_int_accepts_datetime_string_and_datetime_instance(): void
    {
        $expected = DateHelper::adToBsInt('2026-04-16');

        $this->assertSame($expected, DateHelper::adToBsInt('2026-04-16 13:24:08'));
        $this->assertSame($expected, DateHelper::adToBsInt(CarbonImmutable::parse('2026-04-16 13:24:08')));
    }

    public function test_normalize_ad_date_rejects_blank_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateHelper::normalizeAdDate('   ');
    }
}
