<?php

namespace Tests\Feature;

use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_second_agent_cannot_accept_an_already_accepted_ticket(): void
    {
        [$agentA, $agentB] = User::factory()->agent()->count(2)->create()->all();
        $ticket = Ticket::factory()->create();

        // Both agents loaded the page while the ticket was unassigned: two stale in-memory copies.
        $copyForB = Ticket::find($ticket->id);
        $this->assertNull($copyForB->assigned_agent_id);

        $service = app(AssignmentService::class);
        $service->accept($ticket, $agentA);

        $lost = false;
        try {
            $service->accept($copyForB, $agentB);
        } catch (TicketAlreadyAssignedException) {
            $lost = true;
        }

        $this->assertTrue($lost, 'The second accept must be rejected.');
        $this->assertSame($agentA->id, $ticket->fresh()->assigned_agent_id);
        $this->assertSame(1, TicketActivity::where('ticket_id', $ticket->id)->where('type', TicketActivity::ASSIGNED)->count());
    }

    public function test_accepting_moves_an_open_ticket_to_in_progress(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create();

        app(AssignmentService::class)->accept($ticket, $agent);

        $this->assertSame('in_progress', $ticket->fresh()->status->value);
    }

    public function test_auto_assign_picks_the_least_loaded_available_agent(): void
    {
        $busy = User::factory()->agent()->create();
        $free = User::factory()->agent()->create();
        $away = User::factory()->agent()->create(['is_available' => false]);

        Ticket::factory()->count(2)->assignedTo($busy)->create();
        $ticket = Ticket::factory()->create();

        app(AssignmentService::class)->autoAssign($ticket);

        $this->assertSame($free->id, $ticket->fresh()->assigned_agent_id);
        $this->assertNotSame($away->id, $ticket->fresh()->assigned_agent_id);
    }

    public function test_auto_assign_breaks_ties_by_who_was_assigned_longest_ago(): void
    {
        $recent = User::factory()->agent()->create(['last_assigned_at' => now()->subMinute()]);
        $older = User::factory()->agent()->create(['last_assigned_at' => now()->subDay()]);

        $ticket = Ticket::factory()->create();
        app(AssignmentService::class)->autoAssign($ticket);

        $this->assertSame($older->id, $ticket->fresh()->assigned_agent_id);
        $this->assertNotSame($recent->id, $ticket->fresh()->assigned_agent_id);
    }

    public function test_auto_assign_returns_null_when_nobody_is_available(): void
    {
        User::factory()->agent()->create(['is_available' => false]);
        $ticket = Ticket::factory()->create();

        $this->assertNull(app(AssignmentService::class)->autoAssign($ticket));
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    public function test_admin_can_reassign_a_ticket_manually(): void
    {
        $admin = User::factory()->admin()->create();
        [$first, $second] = User::factory()->agent()->count(2)->create()->all();
        $ticket = Ticket::factory()->assignedTo($first)->create();

        $this->actingAs($admin)
            ->post(route('tickets.assign', $ticket), ['agent_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, $ticket->fresh()->assigned_agent_id);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id, 'type' => 'assigned', 'old_value' => $first->name, 'new_value' => $second->name,
        ]);
    }
}