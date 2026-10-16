<?php

namespace Tests\Unit;

use App\Services\Sla\BusinessHoursCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BusinessHoursCalculatorTest extends TestCase
{
    private function calc(): BusinessHoursCalculator
    {
        return new BusinessHoursCalculator([0, 1, 2, 3, 4], '09:00', '18:00'); // Sun–Thu
    }

    private function at(string $dt): CarbonImmutable
    {
        return CarbonImmutable::parse($dt);
    }

    public function test_within_the_same_business_day(): void
    {
        // Sunday 10:00 + 2h
        $due = $this->calc()->addMinutes($this->at('2026-10-11 10:00'), 120);
        $this->assertSame('2026-10-11 12:00:00', $due->toDateTimeString());
    }

    public function test_spills_over_the_weekend(): void
    {
        // Thursday 17:00 + 2h → 1h Thursday, 1h on Sunday → Sunday 10:00
        $due = $this->calc()->addMinutes($this->at('2026-10-15 17:00'), 120);
        $this->assertSame('2026-10-18 10:00:00', $due->toDateTimeString());
    }

    public function test_created_after_hours_starts_next_business_day(): void
    {
        // Thursday 19:00 + 1h → Sunday 10:00
        $due = $this->calc()->addMinutes($this->at('2026-10-15 19:00'), 60);
        $this->assertSame('2026-10-18 10:00:00', $due->toDateTimeString());
    }

    public function test_created_on_weekend_starts_next_business_day(): void
    {
        // Friday 10:00 + 1h → Sunday 10:00
        $due = $this->calc()->addMinutes($this->at('2026-10-16 10:00'), 60);
        $this->assertSame('2026-10-18 10:00:00', $due->toDateTimeString());
    }

    public function test_ending_exactly_at_closing_time(): void
    {
        $due = $this->calc()->addMinutes($this->at('2026-10-15 16:00'), 120);
        $this->assertSame('2026-10-15 18:00:00', $due->toDateTimeString());
    }

    public function test_long_sla_spans_multiple_days(): void
    {
        // 18 business hours from Sunday 09:00 → Monday 18:00 (9h + 9h)
        $due = $this->calc()->addMinutes($this->at('2026-10-11 09:00'), 18 * 60);
        $this->assertSame('2026-10-12 18:00:00', $due->toDateTimeString());
    }
}