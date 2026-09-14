<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketHistory>
 */
class TicketHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'field' => 'status',
            'from_value' => 'open',
            'to_value' => 'in_progress',
        ];
    }
}
