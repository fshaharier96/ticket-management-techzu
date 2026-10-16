<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-2026-' . fake()->unique()->numerify('######'),
            'subject' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'category_id' => Category::factory(),
            'customer_id' => User::factory(),
            'sla_due_at' => now()->addDay(),
        ];
    }

    public function assignedTo(User $agent): static
    {
        return $this->state([
            'assigned_agent_id' => $agent->id,
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(['status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    }
}