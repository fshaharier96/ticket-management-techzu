<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReplyRequest;
use App\Models\Ticket;
use App\Services\ReplyService;
use Illuminate\Http\RedirectResponse;

class TicketReplyController extends Controller
{
    public function store(StoreReplyRequest $request, Ticket $ticket, ReplyService $replies): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $internal = $request->boolean('is_internal');
        if ($internal) {
            $this->authorize('addInternalNote', $ticket); // customers get a 403 here
        }

        $replies->add(
            $ticket,
            $request->user(),
            $request->validated('body'),
            $internal,
            $request->file('attachments', []),
        );

        return redirect()
            ->to(route('tickets.show', $ticket) . '#conversation')
            ->with('success', $internal ? 'Internal note added.' : 'Reply sent.');
    }
}