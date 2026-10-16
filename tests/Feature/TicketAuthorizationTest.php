<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $stranger = User::factory()->customer()->create();

        $this->actingAs($stranger)->get(route('tickets.show', $ticket))->assertForbidden();
    }

    public function test_customer_list_only_contains_their_own_tickets(): void
    {
        $mine = Ticket::factory()->create();
        $theirs = Ticket::factory()->create();

        $this->actingAs($mine->customer)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee($mine->ticket_number)
            ->assertDontSee($theirs->ticket_number);
    }

    public function test_customer_never_sees_internal_notes(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($agent)->create();

        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'body' => 'public answer', 'is_internal' => false]);
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'body' => 'secret staff note', 'is_internal' => true]);

        $this->actingAs($ticket->customer)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('public answer')
            ->assertDontSee('secret staff note');

        $this->actingAs($agent)
            ->get(route('tickets.show', $ticket))
            ->assertSee('secret staff note');
    }

    public function test_customer_cannot_post_an_internal_note(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($ticket->customer)
            ->post(route('tickets.replies.store', $ticket), ['body' => 'sneaky', 'is_internal' => 1])
            ->assertForbidden();

        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_customer_cannot_download_a_file_attached_to_an_internal_note(): void
    {
        Storage::fake('local');

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($agent)->create();
        $note = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'body' => 'note', 'is_internal' => true]);

        Storage::disk('local')->put("tickets/{$ticket->id}/secret.txt", 'top secret');
        $attachment = Attachment::create([
            'ticket_id' => $ticket->id, 'ticket_reply_id' => $note->id, 'uploaded_by' => $agent->id,
            'path' => "tickets/{$ticket->id}/secret.txt", 'original_name' => 'secret.txt',
            'mime_type' => 'text/plain', 'size' => 10,
        ]);

        $this->actingAs($ticket->customer)->get(route('attachments.download', $attachment))->assertForbidden();
        $this->actingAs($agent)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_customer_cannot_accept_or_assign_tickets(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($ticket->customer)->post(route('tickets.accept', $ticket))->assertForbidden();
        $this->actingAs($ticket->customer)
            ->post(route('tickets.assign', $ticket), ['agent_id' => $agent->id])
            ->assertForbidden();
    }

    public function test_agent_cannot_reply_on_a_ticket_assigned_to_someone_else(): void
    {
        [$owner, $other] = User::factory()->agent()->count(2)->create()->all();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        $this->actingAs($other)
            ->post(route('tickets.replies.store', $ticket), ['body' => 'hello'])
            ->assertForbidden();
    }

    public function test_customer_cannot_change_priority_by_posting_extra_fields(): void
    {
        $ticket = Ticket::factory()->create(['priority' => 'low']);

        $this->actingAs($ticket->customer)->put(route('tickets.update', $ticket), [
            'subject' => 'Edited subject',
            'description' => 'Edited description',
            'priority' => 'critical', // must be ignored
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('Edited subject', $ticket->subject);
        $this->assertSame('low', $ticket->priority->value);
    }

    public function test_csv_import_is_admin_only(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get(route('imports.index'))->assertForbidden();
        $this->actingAs(User::factory()->agent()->create())->get(route('imports.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('imports.index'))->assertOk();
    }
}