<?php

namespace App\Services;

use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Database\Eloquent\Collection;

class SlaEscalationService
{
    public function __construct(private ActivityLogger $activity) {}

    /** @return array{warned:int, breached:int} */
    public function run(): array
    {
        return [
            'warned' => $this->warnApproaching(),
            'breached' => $this->escalateBreached(),
        ];
    }

    private function warnApproaching(): int
    {
        $count = 0;

        Ticket::query()
            ->active()
            ->whereNull('sla_warned_at')
            ->whereNull('sla_breached_at')
            ->whereBetween('sla_due_at', [now(), now()->addMinutes(config('sla.approaching_minutes'))])
            ->chunkById(200, function (Collection $tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    // Claim the transition; 0 rows means another run already handled it.
                    $claimed = Ticket::whereKey($ticket->id)->whereNull('sla_warned_at')
                        ->update(['sla_warned_at' => now()]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $this->activity->log($ticket, TicketActivity::SLA_APPROACHING);
                    event(new TicketSlaApproaching($ticket->refresh()));
                    $count++;
                }
            });

        return $count;
    }

    private function escalateBreached(): int
    {
        $count = 0;

        Ticket::query()
            ->active()
            ->whereNull('sla_breached_at')
            ->where('sla_due_at', '<', now())
            ->chunkById(200, function (Collection $tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $claimed = Ticket::whereKey($ticket->id)->whereNull('sla_breached_at')
                        ->update(['sla_breached_at' => now(), 'escalated_at' => now()]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $this->activity->log($ticket, TicketActivity::SLA_BREACHED);
                    $this->activity->log($ticket, TicketActivity::ESCALATED, null, null, null, ['reason' => 'SLA breached']);
                    event(new TicketSlaBreached($ticket->refresh()));
                    $count++;
                }
            });

        return $count;
    }
}