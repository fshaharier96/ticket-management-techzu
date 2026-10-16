<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

class BusinessHoursCalculator
{
    /**
     * @param  int[]  $days  0 = Sunday ... 6 = Saturday
     */
    public function __construct(
        private array $days,
        private string $start, // 'H:i'
        private string $end,   // 'H:i'
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('sla.business_days'), config('sla.start'), config('sla.end'));
    }

    /**
     * Add business minutes to a moment, skipping closed time.
     */
    public function addMinutes(CarbonInterface $from, int $minutes): CarbonImmutable
    {
        $cursor = $this->nextOpenMoment(CarbonImmutable::instance($from));
        $remaining = $minutes * 60; // work in seconds to avoid rounding

        while (true) {
            $closing = $this->atTime($cursor, $this->end);
            $available = $closing->getTimestamp() - $cursor->getTimestamp();

            if ($remaining <= $available) {
                return $cursor->addSeconds($remaining);
            }

            $remaining -= $available;
            $cursor = $this->nextOpenMoment($closing);
        }
    }

    /**
     * The given moment if the office is open, otherwise the next opening time.
     */
    private function nextOpenMoment(CarbonImmutable $t): CarbonImmutable
    {
        for ($i = 0; $i < 14; $i++) { // bound protects against an empty config
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

        throw new RuntimeException('No business days configured.');
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