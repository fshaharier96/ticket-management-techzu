<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_breached_ticket_is_escalated_exactly_once(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assignedTo($agent)->create(['sla_due_at' => now()->subMinutes(5)]);

        $this->artisan('tickets:check-sla')->assertSuccessful();
        $this->artisan('tickets:check-sla')->assertSuccessful(); // second run must change nothing

        $ticket->refresh();
        $this->assertNotNull($ticket->sla_breached_at);
        $this->assertNotNull($ticket->escalated_at);
        $this->assertSame(1, TicketActivity::where('ticket_id', $ticket->id)->where('type', TicketActivity::ESCALATED)->count());
        Notification::assertSentToTimes($agent, TicketNotification::class, 1);
    }

    public function test_an_approaching_ticket_is_warned_once_and_not_escalated(): void
    {
        $agent = User::factory()->agent()->create();
        $soon = Ticket::factory()->assignedTo($agent)->create(['sla_due_at' => now()->addMinutes(30)]);
        $later = Ticket::factory()->assignedTo($agent)->create(['sla_due_at' => now()->addHours(5)]);

        $this->artisan('tickets:check-sla')->assertSuccessful();
        $this->artisan('tickets:check-sla')->assertSuccessful();

        $soon->refresh();
        $this->assertNotNull($soon->sla_warned_at);
        $this->assertNull($soon->sla_breached_at);
        $this->assertSame(1, TicketActivity::where('ticket_id', $soon->id)->where('type', TicketActivity::SLA_APPROACHING)->count());
        Notification::assertSentToTimes($agent, TicketNotification::class, 1);

        $this->assertNull($later->refresh()->sla_warned_at);
    }

    public function test_finished_tickets_are_ignored(): void
    {
        $ticket = Ticket::factory()->resolved()->create(['sla_due_at' => now()->subDay()]);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        $this->assertNull($ticket->refresh()->sla_breached_at);
        Notification::assertNothingSent();
    }

    public function test_sla_status_helpers_classify_tickets(): void
    {
        $within = Ticket::factory()->create(['sla_due_at' => now()->addHours(5)]);
        $approaching = Ticket::factory()->create(['sla_due_at' => now()->addMinutes(20)]);
        $breached = Ticket::factory()->create(['sla_due_at' => now()->subMinute()]);

        $this->assertSame('within', $within->slaStatus());
        $this->assertSame('approaching', $approaching->slaStatus());
        $this->assertSame('breached', $breached->slaStatus());

        $this->assertSame([$breached->id], Ticket::slaBreached()->pluck('id')->all());
        $this->assertSame([$approaching->id], Ticket::slaApproaching()->pluck('id')->all());
        $this->assertSame([$within->id], Ticket::slaWithin()->pluck('id')->all());
    }
}
