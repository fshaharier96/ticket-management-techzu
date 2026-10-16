<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $event,   // created | assigned | agent_replied | customer_replied | resolved | sla_approaching | sla_breached
        public string $message,
    ) {
        // Don't let the worker pick this up before the surrounding DB transaction has committed.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] " . ucfirst(str_replace('_', ' ', $this->event)))
            ->greeting("Hello {$notifiable->name},")
            ->line($this->message)
            ->action('View ticket', route('tickets.show', $this->ticket));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'event' => $this->event,
            'message' => $this->message,
        ];
    }
}