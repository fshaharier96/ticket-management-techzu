<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // the list itself is scoped with Ticket::visibleTo()
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() || $ticket->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($ticket->status->isFinished()) {
            return $user->isAdmin();
        }

        return match (true) {
            $user->isAdmin() => true,
            $user->isAgent() => $ticket->assigned_agent_id === $user->id,
            default => $ticket->customer_id === $user->id,
        };
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return ! $ticket->status->isFinished() && $this->isOwnerStaff($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        if ($user->isCustomer()) {
            return $ticket->customer_id === $user->id && $ticket->status === TicketStatus::Resolved;
        }

        return $this->isOwnerStaff($user, $ticket);
    }

    /** An agent may take an unassigned, unfinished ticket. */
    public function accept(User $user, Ticket $ticket): bool
    {
        return $user->isAgent()
            && $ticket->assigned_agent_id === null
            && ! $ticket->status->isFinished();
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && ! $ticket->status->isFinished();
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        return $user->isCustomer()
            ? $ticket->customer_id === $user->id
            : $this->isOwnerStaff($user, $ticket);
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->reply($user, $ticket);
    }

    /** Admin, or the agent the ticket is assigned to. */
    private function isOwnerStaff(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $ticket->assigned_agent_id === $user->id);
    }
}