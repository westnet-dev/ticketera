<?php

namespace Database\Factories;

use App\Enums\LinearLinkSource;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketLinearLink>
 */
class TicketLinearLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifier = 'GES-'.fake()->unique()->numberBetween(1, 99999);

        return [
            'ticket_id' => Ticket::factory(),
            'linear_issue_id' => fake()->uuid(),
            'identifier' => $identifier,
            'title' => fake()->sentence(5),
            'url' => "https://linear.app/acme/issue/{$identifier}",
            'state_name' => 'In Progress',
            'state_type' => 'started',
            'assignee_name' => fake()->name(),
            'source' => LinearLinkSource::Manual,
            'linked_by' => null,
            'synced_at' => now(),
        ];
    }

    /**
     * Indicate that the link was detected from the ticket URL attached in Linear.
     */
    public function detected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => LinearLinkSource::Attachment,
        ]);
    }
}
