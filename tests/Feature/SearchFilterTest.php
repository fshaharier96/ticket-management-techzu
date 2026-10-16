<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_narrow_the_results(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $printer = Ticket::factory()->create(['ticket_number' => 'TKT-2026-000010', 'subject' => 'Printer on fire']);
        $login = Ticket::factory()->assignedTo($agent)->create([
            'ticket_number' => 'TKT-2026-000011', 'subject' => 'Cannot login', 'sla_due_at' => now()->subHour(),
        ]);

        $this->actingAs($admin);

        // keyword
        $this->get(route('tickets.index', ['q' => 'printer']))->assertSee('TKT-2026-000010')->assertDontSee('TKT-2026-000011');
        // ticket id
        $this->get(route('tickets.index', ['q' => 'TKT-2026-000011']))->assertSee('TKT-2026-000011')->assertDontSee('TKT-2026-000010');
        // SLA status
        $this->get(route('tickets.index', ['sla' => 'breached']))->assertSee('TKT-2026-000011')->assertDontSee('TKT-2026-000010');
        // unassigned
        $this->get(route('tickets.index', ['agent' => 'unassigned']))->assertSee('TKT-2026-000010')->assertDontSee('TKT-2026-000011');
        // specific agent
        $this->get(route('tickets.index', ['agent' => $agent->id]))->assertSee('TKT-2026-000011')->assertDontSee('TKT-2026-000010');
    }

    public function test_status_filter_overrides_the_active_tab(): void
    {
        $admin = User::factory()->admin()->create();
        $done = Ticket::factory()->resolved()->create(['ticket_number' => 'TKT-2026-000020']);

        $this->actingAs($admin)
            ->get(route('tickets.index', ['status' => 'resolved']))
            ->assertSee($done->ticket_number);
    }

    public function test_the_ticket_list_does_not_run_n_plus_one_queries(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->count(10)->create();

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
        $queries = count(DB::getQueryLog());

        // With 10 rows an N+1 would cost 30+ queries; eager loading keeps it constant.
        $this->assertLessThan(20, $queries, "List page ran {$queries} queries");
    }
}