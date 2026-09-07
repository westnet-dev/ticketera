<?php

namespace Database\Factories;

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'priority' => fake()->numberBetween(1, 10),
            'urgency' => fake()->numberBetween(1, 10),
            'impact' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(['open', 'in_progress', 'paused', 'resolved', 'cancelled']),
            'triage_status' => TriageStatus::Approved,
            'assigned_to' => null,
        ];
    }

    /**
     * Indicate that the ticket is an unsubmitted draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'description' => null,
            'priority' => 5,
            'urgency' => 5,
            'impact' => 5,
        ]);
    }
}
