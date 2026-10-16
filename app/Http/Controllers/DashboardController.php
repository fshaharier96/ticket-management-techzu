<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $now = now();
        $soon = now()->addMinutes(config('sla.approaching_minutes'));
        $active = "'" . implode("','", TicketStatus::activeValues()) . "'"; // constants, not user input
        $finished = "'" . implode("','", TicketStatus::finishedValues()) . "'";

        // ONE query for all the counters
        $row = Ticket::query()
            ->visibleTo($user)
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(status IN ($active)), 0) AS open_total,
                COALESCE(SUM(status IN ($active) AND assigned_agent_id IS NULL), 0) AS unassigned,
                COALESCE(SUM(status IN ($active) AND priority = 'critical'), 0) AS critical,
                COALESCE(SUM(status IN ($active) AND sla_due_at < ?), 0) AS sla_breached,
                COALESCE(SUM(status IN ($active) AND sla_due_at >= ? AND sla_due_at <= ?), 0) AS sla_approaching,
                COALESCE(SUM(status IN ($finished)), 0) AS resolved
            ", [$now, $now, $soon])
            ->first();

        $stats = collect((array) $row)->map(fn ($v) => (int) $v);

        $byPriority = Ticket::query()->visibleTo($user)->active()->toBase()
            ->selectRaw('priority, COUNT(*) AS total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $byAgent = $user->isStaff()
            ? Ticket::query()->active()->toBase()
                ->join('users', 'users.id', '=', 'tickets.assigned_agent_id')
                ->selectRaw('users.name, COUNT(*) AS total')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->get()
            : collect();

        // "Needs attention": already breached or about to be, closest deadline first
        $attention = Ticket::query()
            ->visibleTo($user)
            ->active()
            ->where('sla_due_at', '<=', $soon)
            ->with('agent:id,name')
            ->orderBy('sla_due_at')
            ->limit(8)
            ->get(['id', 'ticket_number', 'subject', 'priority', 'status', 'assigned_agent_id', 'sla_due_at']);

        return view('dashboard', compact('stats', 'byPriority', 'byAgent', 'attention'));
    }
}