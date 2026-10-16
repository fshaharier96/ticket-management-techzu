<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketReplied;
use App\Events\TicketResolved;
use App\Events\TicketSlaApproaching;
use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

class TicketNotificationSubscriber
{
    /** Registered in AppServiceProvider::boot() with Event::subscribe(). */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketCreated::class => 'onCreated',
            TicketAssigned::class => 'onAssigned',
            TicketReplied::class => 'onReplied',
            TicketResolved::class => 'onResolved',
            TicketSlaApproaching::class => 'onSlaApproaching',
            TicketSlaBreached::class => 'onSlaBreached',
        ];
    }

    public function onCreated(TicketCreated $e): void
    {
        $t = $e->ticket;

        $this->notify(
            [$t->customer, ...User::admins()->get()->all()],
            $t, 'created', "Ticket {$t->ticket_number} was created: {$t->subject}",
        );
    }

    public function onAssigned(TicketAssigned $e): void
    {
        $t = $e->ticket;
        $agentName = $t->agent?->name ?? 'an agent';

        $this->notify(
            [$t->agent, $t->customer],
            $t, 'assigned', "Ticket {$t->ticket_number} is now assigned to {$agentName}.",
            exceptUserId: $e->actor?->id,
        );
    }

    public function onReplied(TicketReplied $e): void
    {
        $t = $e->ticket;
        $customerReplied = $e->reply->user_id === $t->customer_id;

        if ($customerReplied) {
            // the assigned agent, or all admins when nobody owns the ticket yet
            $recipients = $t->agent ? [$t->agent] : User::admins()->get()->all();
            $this->notify($recipients, $t, 'customer_replied', "The customer replied on {$t->ticket_number}.");

            return;
        }

        $this->notify([$t->customer], $t, 'agent_replied', "Support replied on {$t->ticket_number}.");
    }

    public function onResolved(TicketResolved $e): void
    {
        $t = $e->ticket;

        $this->notify([$t->customer], $t, 'resolved', "Ticket {$t->ticket_number} was marked as resolved.");
    }

    public function onSlaApproaching(TicketSlaApproaching $e): void
    {
        $t = $e->ticket;

        $this->notify(
            [$t->agent, ...User::admins()->get()->all()],
            $t, 'sla_approaching', "Ticket {$t->ticket_number} is approaching its SLA deadline.",
        );
    }

    public function onSlaBreached(TicketSlaBreached $e): void
    {
        $t = $e->ticket;

        $this->notify(
            [$t->agent, ...User::admins()->get()->all()],
            $t, 'sla_breached', "Ticket {$t->ticket_number} has breached its SLA and was escalated.",
        );
    }

    private function notify(iterable $users, Ticket $ticket, string $event, string $message, ?int $exceptUserId = null): void
    {
        $recipients = collect($users)
            ->filter()
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $exceptUserId);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketNotification($ticket, $event, $message));
        }
    }
}