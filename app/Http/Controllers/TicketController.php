<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'category_id' => ['nullable', 'integer'],
            'agent' => ['nullable', 'string', 'max:20'],
            'customer' => ['nullable', 'string', 'max:100'],
            'sla' => ['nullable', 'in:within,approaching,breached'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'scope' => ['nullable', 'in:active,resolved,all'],
        ]);

        // An explicit status filter overrides the tab (otherwise "resolved" under the "active" tab is always empty)
        $scope = ! empty($filters['status']) ? 'all' : ($filters['scope'] ?? 'active');

        $query = Ticket::query()
            ->visibleTo($user)                                   // authorization lives in the query too
            ->with(['customer:id,name', 'agent:id,name', 'category:id,name']) // no N+1 in the list
            ->filter($filters);

        $query = match ($scope) {
            'resolved' => $query->finished()->orderByDesc('resolved_at'),
            'all' => $query->latest(),
            default => $query->active()->orderBy('sla_due_at'),  // "upcoming": closest deadline first
        };

        return view('tickets.index', [
            'tickets' => $query->paginate(15)->withQueryString(),
            'scope' => $scope,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'agents' => $user->isCustomer()
                ? collect()
                : User::where('role', Role::Agent->value)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create', [
            'ticket' => null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'customers' => $request->user()->isStaff()
                ? User::where('role', Role::Customer->value)->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = $this->tickets->create(
            $request->user(),
            $request->safe()->except('attachments'),
            $request->file('attachments', []),
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created.");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $user = $request->user();

        $ticket->load(['customer:id,name,email', 'agent:id,name', 'category:id,name']);

        $replies = $ticket->replies()
            ->with(['user:id,name,role', 'attachments'])               // 2 queries, however many replies
            ->when($user->isCustomer(), fn ($q) => $q->public())        // internal notes never leave the server
            ->orderBy('created_at')->orderBy('id')
            ->paginate(20, ['*'], 'replies_page');

        return view('tickets.show', [
            'ticket' => $ticket,
            'replies' => $replies,
            'ticketAttachments' => $ticket->attachments()->whereNull('ticket_reply_id')->get(),
            'activities' => $user->isStaff()
                ? $ticket->activities()->with('user:id,name')->orderBy('created_at')->orderBy('id')->limit(200)->get()
                : collect(),
            'agents' => $user->isAdmin()
                ? User::where('role', Role::Agent->value)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', [
            'ticket' => $ticket,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'customers' => collect(),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->tickets->update($ticket, $request->user(), $request->validated());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('resolve', $ticket);

        $this->tickets->changeStatus($ticket, TicketStatus::Resolved, $request->user());

        return back()->with('success', 'Ticket marked as resolved.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('close', $ticket);

        $this->tickets->changeStatus($ticket, TicketStatus::Closed, $request->user());

        return back()->with('success', 'Ticket closed.');
    }
}