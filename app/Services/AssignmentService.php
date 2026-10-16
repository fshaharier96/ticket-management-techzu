<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignmentService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * An agent accepts an unassigned ticket.
     *
     * @throws TicketAlreadyAssignedException when someone else got there first
     */
    public function accept(Ticket $ticket, User $agent): Ticket
    {
        return $this->claim($ticket, $agent, $agent);
    }

    /**
     * Assign to the least-loaded available agent. Returns null when no agent is available.
     *
     * @throws TicketAlreadyAssignedException
     */
    public function autoAssign(Ticket $ticket, ?User $actor = null): ?Ticket
    {
        $agent = $this->pickAgent();

        return $agent ? $this->claim($ticket, $agent, $actor) : null;
    }

    /**
     * Admin override: assign or re-assign regardless of the current owner.
     * Uses a row lock so concurrent admin actions are serialized.
     */
    public function assign(Ticket $ticket, User $agent, User $actor): Ticket
    {
        if ($agent->role !== Role::Agent) {
            throw new RuntimeException('Tickets can only be assigned to agents.');
        }

        $fresh = DB::transaction(function () use ($ticket, $agent, $actor) {
            $locked = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status->isFinished()) {
                throw new RuntimeException('A resolved or closed ticket cannot be assigned.');
            }

            $oldAgentName = $locked->agent?->name;
            $oldStatus = $locked->status;

            $locked->assigned_agent_id = $agent->id;
            if ($oldStatus === TicketStatus::Open) {
                $locked->status = TicketStatus::InProgress;
            }
            $locked->save();

            $this->recordAssignment($locked, $agent, $actor, $oldAgentName, $oldStatus);

            return $locked;
        });

        event(new TicketAssigned($fresh, $actor));

        return $fresh;
    }

    /**
     * THE CONCURRENCY SOLUTION.
     *
     * Two agents click "Accept" at the same moment. Both requests load the ticket, both see it as
     * unassigned. Instead of "read, check, then write" (a race), we make the check part of the write:
     *
     *   UPDATE tickets SET assigned_agent_id = ? WHERE id = ? AND assigned_agent_id IS NULL
     *
     * MySQL (InnoDB) takes a row lock for the UPDATE, so the second statement waits for the first to commit,
     * then re-evaluates its WHERE clause, finds the ticket already assigned, and affects 0 rows.
     * The number of affected rows tells us who won; the loser gets a friendly error.
     */
    private function claim(Ticket $ticket, User $agent, ?User $actor): Ticket
    {
        $oldStatus = $ticket->status;

        $fresh = DB::transaction(function () use ($ticket, $agent, $actor, $oldStatus) {
            $open = TicketStatus::Open->value;
            $inProgress = TicketStatus::InProgress->value;

            $affected = Ticket::query()
                ->whereKey($ticket->getKey())
                ->whereNull('assigned_agent_id')
                ->whereIn('status', TicketStatus::activeValues())
                ->update([
                    'assigned_agent_id' => $agent->id,
                    'status' => DB::raw("CASE WHEN status = '{$open}' THEN '{$inProgress}' ELSE status END"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw new TicketAlreadyAssignedException();
            }

            $fresh = $ticket->fresh();
            $this->recordAssignment($fresh, $agent, $actor, null, $oldStatus);

            return $fresh;
        });

        event(new TicketAssigned($fresh, $actor));

        return $fresh;
    }

    /**
     * Strategy: fewest ACTIVE tickets among AVAILABLE agents.
     * Ties are broken by "assigned longest ago" (never-assigned agents first), which gives round-robin behaviour.
     */
    private function pickAgent(): ?User
    {
        return User::query()
            ->where('role', Role::Agent->value)
            ->where('is_available', true)
            ->withCount(['assignedTickets as active_tickets_count' => fn ($q) => $q->active()])
            ->orderBy('active_tickets_count')
            ->orderBy('last_assigned_at') // MySQL sorts NULL first → never-assigned agents win ties
            ->orderBy('id')
            ->first();
    }

    private function recordAssignment(Ticket $ticket, User $agent, ?User $actor, ?string $oldAgentName, TicketStatus $oldStatus): void
    {
        $this->activity->log($ticket, TicketActivity::ASSIGNED, $actor, $oldAgentName, $agent->name);

        if ($oldStatus === TicketStatus::Open && $ticket->status === TicketStatus::InProgress) {
            $this->activity->log($ticket, TicketActivity::STATUS_CHANGED, $actor, $oldStatus->label(), $ticket->status->label());
        }

        User::whereKey($agent->id)->update(['last_assigned_at' => now()]);
    }
}