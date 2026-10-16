<?php

namespace App\Console\Commands;

use App\Services\SlaEscalationService;
use Illuminate\Console\Command;

class CheckSla extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Warn about tickets approaching their SLA and escalate tickets that breached it';

    public function handle(SlaEscalationService $service): int
    {
        $result = $service->run();

        $this->info("Warned: {$result['warned']}, escalated: {$result['breached']}");

        return self::SUCCESS;
    }
}