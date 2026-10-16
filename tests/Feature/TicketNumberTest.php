<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\TicketNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_and_formatted(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 10:00'));
        $generator = app(TicketNumberGenerator::class);

        $this->assertSame('TKT-2026-000001', $generator->next());
        $this->assertSame('TKT-2026-000002', $generator->next());
    }

    public function test_the_sequence_restarts_every_year(): void
    {
        $generator = app(TicketNumberGenerator::class);

        $this->travelTo(Carbon::parse('2026-12-31 23:00'));
        $this->assertSame('TKT-2026-000001', $generator->next());

        $this->travelTo(Carbon::parse('2027-01-01 00:30'));
        $this->assertSame('TKT-2027-000001', $generator->next());
    }

    public function test_reserve_returns_a_contiguous_block(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 10:00'));
        $generator = app(TicketNumberGenerator::class);

        $this->assertSame(
            ['TKT-2026-000001', 'TKT-2026-000002', 'TKT-2026-000003'],
            $generator->reserve(3),
        );
        $this->assertSame('TKT-2026-000004', $generator->next());
    }

    public function test_the_database_refuses_a_duplicate_number(): void
    {
        Ticket::factory()->create(['ticket_number' => 'TKT-2026-000001']);

        $this->expectException(QueryException::class);

        Ticket::factory()->create(['ticket_number' => 'TKT-2026-000001']);
    }
}