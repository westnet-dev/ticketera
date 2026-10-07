<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\Level;
use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
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
            'importance' => fake()->randomElement(Level::cases()),
            'urgency' => fake()->randomElement(Level::cases()),
            'impact' => fake()->randomElement(Level::cases()),
            'status' => fake()->randomElement(['open', 'in_progress', 'paused', 'awaiting_response', 'pending_deploy', 'resolved', 'cancelled']),
            'triage_status' => TriageStatus::Approved,
            'validation_status' => ValidationStatus::NotRequested,
            'resolution_rating' => null,
            'validated_at' => null,
            'assigned_to' => null,
        ];
    }

    /**
     * Indicate that the ticket was resolved and is waiting on its author's validation.
     */
    public function awaitingValidation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'resolved',
            'validation_status' => ValidationStatus::Pending,
            'resolution_rating' => null,
            'validated_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket's author confirmed the resolution and rated it.
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'resolved',
            'validation_status' => ValidationStatus::Confirmed,
            'resolution_rating' => fake()->numberBetween(1, 5),
            'validated_at' => now(),
        ]);
    }

    /**
     * Indicate that the work is done and only the deploy to production is missing.
     */
    public function pendingDeploy(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending_deploy',
        ]);
    }

    /**
     * Indicate that the team is waiting on the ticket's author to reply.
     */
    public function awaitingResponse(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'awaiting_response',
        ]);
    }

    /**
     * Indicate that an admin estimated the ticket's difficulty.
     */
    public function withDifficulty(?Difficulty $difficulty = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'difficulty' => $difficulty ?? fake()->randomElement(Difficulty::cases()),
        ]);
    }

    /**
     * Indicate that the ticket is an unsubmitted draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'description' => null,
            'importance' => Level::Medium,
            'urgency' => Level::Medium,
            'impact' => Level::Medium,
        ]);
    }
}
