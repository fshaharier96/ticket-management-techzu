<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SlaPolicySeeder::class);
        Notification::fake();
    }

    public function test_customer_creates_a_ticket_with_number_sla_and_history(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00')); // Thursday 5 PM

        $category = Category::factory()->create();
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)->post(route('tickets.store'), [
            'subject' => 'Cannot log in',
            'description' => 'I get an error 500',
            'priority' => 'critical',
            'category_id' => $category->id,
        ]);

        $ticket = Ticket::firstOrFail();

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame('TKT-2026-000001', $ticket->ticket_number);
        $this->assertSame('open', $ticket->status->value);
        $this->assertSame($customer->id, $ticket->customer_id);
        // 2 business hours from Thursday 17:00 → Sunday 10:00
        $this->assertSame('2026-10-18 10:00:00', $ticket->sla_due_at->toDateTimeString());
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id, 'type' => 'created', 'user_id' => $customer->id,
        ]);
    }

    public function test_validation_rejects_an_unknown_priority(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), [
                'subject' => 'x', 'description' => 'y', 'priority' => 'urgent', 'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('priority');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_changing_priority_recalculates_the_sla_and_logs_it(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 10:00')); // Sunday 10:00

        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'low', 'created_at' => now(), 'sla_due_at' => now()->addDays(2),
        ]);

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => 'critical',
            'category_id' => $ticket->category_id,
            'status' => 'open',
        ])->assertRedirect();

        $this->assertSame('2026-10-11 12:00:00', $ticket->refresh()->sla_due_at->toDateTimeString());
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id, 'type' => 'priority_changed', 'old_value' => 'Low', 'new_value' => 'Critical',
        ]);
    }
}