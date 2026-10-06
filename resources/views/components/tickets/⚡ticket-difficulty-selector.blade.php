<?php

use App\Enums\Difficulty;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    /**
     * An empty value clears the estimate; anything off the Fibonacci scale is ignored.
     */
    public function setDifficulty(string $value): void
    {
        Gate::authorize('estimate', $this->ticket);

        $difficulty = $value === '' ? null : Difficulty::tryFrom((int) $value);

        if ($value !== '' && ($difficulty === null || (string) $difficulty->value !== $value)) {
            return;
        }

        if ($difficulty === $this->ticket->difficulty) {
            return;
        }

        DB::transaction(fn () => $this->ticket->update(['difficulty' => $difficulty]));
    }
};
?>

<div>
    <flux:select size="sm" wire:change="setDifficulty($event.target.value)">
        <flux:select.option value="" :selected="$ticket->difficulty === null">
            {{ __('Sin estimar') }}
        </flux:select.option>
        @foreach (Difficulty::cases() as $difficulty)
            <flux:select.option value="{{ $difficulty->value }}" :selected="$ticket->difficulty === $difficulty">
                {{ $difficulty->label() }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
