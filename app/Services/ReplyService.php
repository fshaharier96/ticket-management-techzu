<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketReplied;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReplyService
{
    public function __construct(
        private ActivityLogger $activity,
        private AttachmentService $attachments,
        private TicketService $tickets,
    ) {}

    /**
     * @param  array  $files  UploadedFile[]
     */
    public function add(Ticket $ticket, User $author, string $body, bool $internal = false, array $files = []): TicketReply
    {
        // Last line of defence: a customer can never create an internal note, whatever the caller passes.
        $internal = $internal && $author->isStaff();

        $reply = DB::transaction(function () use ($ticket, $author, $body, $internal, $files) {
            $reply = $ticket->replies()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $internal,
            ]);

            if ($files) {
                $this->attachments->storeMany($ticket, $author, $files, $reply);
            }

            $this->activity->log(
                $ticket,
                $internal ? TicketActivity::NOTE_ADDED : TicketActivity::REPLIED,
                $author,
                meta: ['reply_id' => $reply->id],
            );

            // A customer answering (or reopening a resolved ticket) puts it back in the agent's court.
            if ($author->isCustomer() && in_array($ticket->status, [TicketStatus::WaitingForCustomer, TicketStatus::Resolved], true)) {
                $this->tickets->changeStatus($ticket, TicketStatus::InProgress, $author);
            }

            return $reply;
        });

        if (! $internal) { // internal notes NEVER trigger notifications
            event(new TicketReplied($ticket, $reply));
        }

        return $reply;
    }
}