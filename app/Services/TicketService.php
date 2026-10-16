<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\Sla\SlaService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private TicketNumberGenerator $numbers,
        private SlaService $sla,
        private ActivityLogger $activity,
        private AttachmentService $attachments,
        private AssignmentService $assignment,
    ) {}

    /**
     * @param  array  $data   validated input
     * @param  array  $files  UploadedFile[]
     */
    public function create(User $actor, array $data, array $files = []): Ticket
    {
        $priority = TicketPriority::from($data['priority']);
        $customerId = $actor->isCustomer() ? $actor->id : (int) $data['customer_id'];

        // Everything that must succeed together is ONE transaction. The ticket-number row lock is
        // taken inside it, so it is held until commit (no duplicates, no gaps on rollback).
        $ticket = DB::transaction(function () use ($actor, $data, $files, $priority, $customerId) {
            $ticket = Ticket::create([
                'ticket_number' => $this->numbers->next(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => TicketStatus::Open,
                'category_id' => $data['category_id'],
                'customer_id' => $customerId,
                'sla_due_at' => $this->sla->dueAt($priority),
            ]);

            $this->activity->log($ticket, TicketActivity::CREATED, $actor);

            if ($files) {
                $this->attachments->storeMany($ticket, $actor, $files);
                $this->activity->log($ticket, TicketActivity::ATTACHMENT_ADDED, $actor, null, null, ['count' => count($files)]);
            }

            return $ticket;
        });

        event(new TicketCreated($ticket));

        if (config('tickets.auto_assign_on_create')) {
            $this->assignment->autoAssign($ticket);
        }

        return $ticket->refresh();
    }

    public function update(Ticket $ticket, User $actor, array $data): Ticket
    {
        DB::transaction(function () use ($ticket, $actor, $data) {
            // Defence in depth: even if a controller passed extra keys, customers can only touch these two fields.
            $fields = Arr::only(
                $data,
                $actor->isCustomer() ? ['subject', 'description'] : ['subject', 'description', 'priority', 'category_id'],
            );

            $oldPriority = $ticket->priority;
            $newPriority = isset($fields['priority']) ? TicketPriority::from($fields['priority']) : $oldPriority;

            $ticket->fill(Arr::except($fields, ['priority']));

            if ($newPriority !== $oldPriority) {
                $ticket->priority = $newPriority;
                $this->recalculateSla($ticket);
            }

            $ticket->save();

            if ($newPriority !== $oldPriority) {
                $this->activity->log($ticket, TicketActivity::PRIORITY_CHANGED, $actor, $oldPriority->label(), $newPriority->label());
            }

            if (! $actor->isCustomer() && isset($data['status'])) {
                $this->changeStatus($ticket, TicketStatus::from($data['status']), $actor);
            }
        });

        return $ticket->refresh();
    }

    public function changeStatus(Ticket $ticket, TicketStatus $new, User $actor): Ticket
    {
        if ($ticket->status === $new) {
            return $ticket;
        }

        $old = $ticket->status;

        DB::transaction(function () use ($ticket, $new, $old, $actor) {
            $ticket->status = $new;
            $ticket->resolved_at = $new->isFinished() ? ($ticket->resolved_at ?? now()) : null;
            $ticket->save();

            $this->activity->log($ticket, TicketActivity::STATUS_CHANGED, $actor, $old->label(), $new->label());
        });

        if ($new === TicketStatus::Resolved) {
            event(new TicketResolved($ticket));
        }

        return $ticket;
    }

    /**
     * Priority changed → the SLA clock restarts from the ORIGINAL creation time with the new rule.
     * If the new deadline is in the future again, the warning/breach flags are cleared so the scheduler
     * can act on the new deadline. (The history still shows the earlier escalation.)
     */
    private function recalculateSla(Ticket $ticket): void
    {
        $ticket->sla_due_at = $this->sla->dueAt($ticket->priority, $ticket->created_at);

        if ($ticket->sla_due_at->isFuture()) {
            $ticket->sla_warned_at = null;
            $ticket->sla_breached_at = null;
            $ticket->escalated_at = null;
        }
    }
}