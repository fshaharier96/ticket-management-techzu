<?php

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SlaService
{
    public function __construct(private BusinessHoursCalculator $calculator) {}

    public function dueAt(TicketPriority $priority, ?CarbonInterface $from = null): CarbonImmutable
    {
        $minutes = SlaPolicy::where('priority', $priority->value)->value('resolution_minutes');

        if ($minutes === null) {
            throw new \RuntimeException("No SLA policy for priority [{$priority->value}].");
        }

        return $this->calculator->addMinutes($from ?? now(), (int) $minutes);
    }
}