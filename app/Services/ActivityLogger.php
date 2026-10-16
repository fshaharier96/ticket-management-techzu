<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class ActivityLogger
{
    public function log(
        Ticket $ticket,
        string $type,
        ?User $actor = null,
        ?string $old = null,
        ?string $new = null,
        array $meta = [],
    ): TicketActivity {
        return TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'old_value' => $old,
            'new_value' => $new,
            'meta' => $meta ?: null,
        ]);
    }
}