<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class TicketAssignmentController extends Controller
{
    public function __construct(private AssignmentService $assignments) {}

    /** An agent takes an unassigned ticket. */
    public function accept(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('accept', $ticket);

        try {
            $this->assignments->accept($ticket, $request->user());
        } catch (TicketAlreadyAssignedException $e) {
            return back()->with('error', $e->getMessage()); // we lost the race: tell the user nicely
        }

        return back()->with('success', 'Ticket accepted. It is now assigned to you.');
    }

    /** Admin assigns manually. */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $data = $request->validate([
            'agent_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Agent->value)],
        ]);

        try {
            $this->assignments->assign($ticket, User::findOrFail($data['agent_id']), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket assigned.');
    }

    /** Admin triggers the automatic strategy. */
    public function auto(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        try {
            $result = $this->assignments->autoAssign($ticket, $request->user());
        } catch (TicketAlreadyAssignedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $result
            ? back()->with('success', 'Ticket auto-assigned.')
            : back()->with('error', 'No available agent to assign this ticket to.');
    }
}