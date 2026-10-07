<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    /**
     * Only active admins other than the assignee can collaborate; anything else is ignored.
     */
    public function addCollaborator(string $userId): void
    {
        Gate::authorize('assign', $this->ticket);

        if ($userId === '' || (int) $userId === $this->ticket->assigned_to) {
            return;
        }

        $user = User::assignable()->find((int) $userId);

        if ($user === null) {
            return;
        }

        DB::transaction(fn () => $this->ticket->addCollaborator($user));
    }

    public function removeCollaborator(int $userId): void
    {
        Gate::authorize('assign', $this->ticket);

        $user = User::withTrashed()->find($userId);

        if ($user === null) {
            return;
        }

        DB::transaction(fn () => $this->ticket->removeCollaborator($user));
    }

    /**
     * Assigning a collaborator takes them off this list, so pick up the change.
     */
    #[On('ticket-assignee-changed')]
    public function refreshTicket(): void
    {
        $this->ticket->refresh();
    }

    public function with(): array
    {
        $collaborators = $this->ticket->collaborators()->orderBy('name')->get();

        // array_filter matters: a NULL inside NOT IN makes SQL return no rows at all.
        $excluded = array_filter([...$collaborators->modelKeys(), $this->ticket->assigned_to]);

        return [
            'collaborators' => $collaborators,
            'candidates' => User::assignable()
                ->whereKeyNot($excluded)
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-2">
    @forelse ($collaborators as $collaborator)
        <div wire:key="collaborator-{{ $collaborator->id }}" class="flex items-center justify-between gap-2">
            <x-user-cell :user="$collaborator" />
            @can('assign', $ticket)
                <flux:button
                    size="xs"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="removeCollaborator({{ $collaborator->id }})"
                    :aria-label="__('Quitar a :name', ['name' => $collaborator->name])"
                />
            @endcan
        </div>
    @empty
        <p class="text-neutral-500 dark:text-neutral-400">{{ __('Sin colaboradores') }}</p>
    @endforelse

    @can('assign', $ticket)
        @if ($candidates->isNotEmpty())
            <flux:select size="sm" wire:change="addCollaborator($event.target.value)" wire:key="add-collaborator-{{ $collaborators->count() }}-{{ $ticket->assigned_to }}">
                <flux:select.option value="" selected>{{ __('Agregar colaborador') }}</flux:select.option>
                @foreach ($candidates as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    @endcan
</div>
