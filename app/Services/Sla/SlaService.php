<?php

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

class SlaService
{
    /** @var array<string,int>|null priority => business minutes (loaded once per instance) */
    private ?array $minutes = null;

    public function __construct(private BusinessHoursCalculator $calculator) {}

    public function dueAt(TicketPriority $priority, ?CarbonInterface $from = null): CarbonImmutable
    {
        $this->minutes ??= SlaPolicy::pluck('resolution_minutes', 'priority')->all();

        $minutes = $this->minutes[$priority->value]
            ?? throw new RuntimeException("No SLA policy is configured for priority [{$priority->value}].");

        return $this->calculator->addMinutes($from ?? now(), (int) $minutes);
    }
}