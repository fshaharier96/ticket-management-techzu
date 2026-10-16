<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Adds "business minutes" to a moment, skipping closed time.
 * Pure class (no database, no globals) so it is trivial to unit test.
 */
class BusinessHoursCalculator
{
    /**
     * @param  int[]  $days   Carbon dayOfWeek values (0 = Sunday ... 6 = Saturday)
     * @param  string $start  'H:i' opening time
     * @param  string $end    'H:i' closing time
     */
    public function __construct(
        private array $days,
        private string $start,
        private string $end,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('sla.business_days'), config('sla.start'), config('sla.end'));
    }

    public function addMinutes(CarbonInterface $from, int $minutes): CarbonImmutable
    {
        $cursor = $this->nextOpenMoment(CarbonImmutable::instance($from));
        $remaining = $minutes * 60; // seconds, to avoid rounding issues

        while (true) {
            $closing = $this->atTime($cursor, $this->end);
            $availableToday = $closing->getTimestamp() - $cursor->getTimestamp();

            if ($remaining <= $availableToday) {
                return $cursor->addSeconds($remaining);
            }

            // Use up today and continue from the next opening time
            $remaining -= $availableToday;
            $cursor = $this->nextOpenMoment($closing);
        }
    }

    /** The given moment if the office is open, otherwise the next opening time. */
    private function nextOpenMoment(CarbonImmutable $t): CarbonImmutable
    {
        for ($i = 0; $i < 14; $i++) { // bounded so an empty configuration cannot loop forever
            if ($this->isBusinessDay($t)) {
                $open = $this->atTime($t, $this->start);
                $close = $this->atTime($t, $this->end);

                if ($t < $open) {
                    return $open;
                }
                if ($t < $close) {
                    return $t;
                }
            }

            $t = $this->atTime($t->addDay(), $this->start);
        }

        throw new RuntimeException('No business days are configured.');
    }

    private function isBusinessDay(CarbonImmutable $t): bool
    {
        return in_array($t->dayOfWeek, $this->days, true);
    }

    private function atTime(CarbonImmutable $t, string $time): CarbonImmutable
    {
        return $t->setTimeFromTimeString($time);
    }
}